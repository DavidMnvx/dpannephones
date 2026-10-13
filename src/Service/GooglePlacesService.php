<?php

namespace App\Service;

use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Avis Google EN DIRECT : la note, le nombre d'avis et les 5 avis de la fiche Google,
 * lus sur l'API Google Places (New) au lieu d'être recopiés à la main.
 *
 *  - Clé de l'API : secret GOOGLE_PLACES_API_KEY (.env.local / .env.prod.local). Vide = pas de direct.
 *  - Fiche lue : paramètre « google_place_id » (Admin > Paramètres > Avis Google).
 *  - Google est lu au plus une fois par heure (cache.app) : la note bouge peu, et c'est ce qui
 *    garde l'appel dans la part gratuite de Google. Après un échec, nouvel essai dix minutes plus tard.
 *  - Google ne donne que les 5 avis qu'il juge les plus pertinents (pas de pagination possible).
 *  - L'admin peut masquer un avis lu en direct (Admin > Avis Google) : les identifiants Google des
 *    avis masqués sont rangés dans le paramètre interne « google_reviews_hidden » (liste JSON).
 *  - Aucune exception ne remonte : sans clé, sans fiche ou si Google ne répond pas,
 *    StaticReviewsProvider retombe sur les avis saisis dans l'admin.
 */
class GooglePlacesService
{
    public const STATE_NO_KEY      = 'sans_cle';
    public const STATE_NO_PLACE    = 'sans_fiche';
    public const STATE_UNREACHABLE = 'injoignable';
    public const STATE_LIVE        = 'en_direct';

    /** La fiche D'Panne Phones (celle du lien Google Maps de l'accueil) ; modifiable dans les paramètres. */
    public const DEFAULT_PLACE_ID = 'ChIJQ8joB7_5yRIRHpgyRx9z26o';
    public const HIDDEN_SETTING   = 'google_reviews_hidden';

    private const ENDPOINT    = 'https://places.googleapis.com/v1/places/';
    private const TTL         = 3600;
    private const TTL_FAILURE = 600;
    /** Au-delà, le visiteur de l'accueil attendrait : on se rabat sur les avis saisis à la main. */
    private const TIMEOUT     = 4;

    /** @var array{place: array|null, error: string|null}|null ce qui a été lu, une fois par requête */
    private ?array $read = null;

    public function __construct(
        private HttpClientInterface $http,
        private CacheInterface $cache,
        private AppSettingService $settings,
        private AppSettingRepository $settingRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        #[Autowire(env: 'GOOGLE_PLACES_API_KEY')] private string $apiKey,
    ) {}

    public function getPlaceId(): ?string
    {
        $placeId = trim($this->settings->getString('google_place_id', self::DEFAULT_PLACE_ID));

        return preg_match('/^[A-Za-z0-9_-]{10,}$/', $placeId) ? $placeId : null;
    }

    /** Où en est le direct : une des constantes STATE_*. */
    public function getState(): string
    {
        return match (true) {
            '' === trim($this->apiKey)           => self::STATE_NO_KEY,
            null === $this->getPlaceId()         => self::STATE_NO_PLACE,
            null === $this->readPlace()['place'] => self::STATE_UNREACHABLE,
            default                              => self::STATE_LIVE,
        };
    }

    /** Pourquoi Google n'a pas pu être lu, en clair pour l'admin, ou null. */
    public function getError(): ?string
    {
        return $this->readPlace()['error'];
    }

    /**
     * La fiche lue chez Google, avis masqués compris (marqués `hidden`), ou null sans direct.
     *
     * @return array{rating: float, total: int, read_at: \DateTimeImmutable, reviews: list<array{key: string, author_name: string, author_url: ?string, rating: int, relative_time: ?string, text: string, profile_photo_url: null, hidden: bool}>}|null
     */
    public function getPlace(): ?array
    {
        $place = $this->readPlace()['place'];
        if (null === $place) {
            return null;
        }

        $hidden = $this->getHiddenKeys();
        foreach ($place['reviews'] as &$review) {
            $review['hidden'] = in_array($review['key'], $hidden, true);
        }
        unset($review);
        $place['read_at'] = new \DateTimeImmutable($place['read_at']);

        return $place;
    }

    /** Oublie la lecture en cache : la prochaine demande relit Google (bouton « Actualiser »). */
    public function refresh(): void
    {
        $placeId = $this->getPlaceId();
        if (null !== $placeId) {
            $this->cache->delete(self::cacheKey($placeId));
        }
        $this->read = null;
    }

    /**
     * Masque l'avis s'il s'affiche, le réaffiche s'il est masqué. Seul un avis que Google
     * donne en ce moment peut être masqué (aucune valeur bricolée n'est gardée en base).
     *
     * @return bool|null l'avis est-il maintenant masqué ? null s'il n'est pas sur la fiche
     */
    public function toggleHidden(string $key): ?bool
    {
        $keys = array_column($this->getPlace()['reviews'] ?? [], 'key');
        if (!in_array($key, $keys, true)) {
            return null;
        }

        $hidden = $this->getHiddenKeys();
        $nowHidden = !in_array($key, $hidden, true);
        $hidden = $nowHidden ? [...$hidden, $key] : array_values(array_diff($hidden, [$key]));

        $setting = $this->settingRepository->findOneBy(['key' => self::HIDDEN_SETTING]);
        if (null === $setting) {
            // Paramètre interne : la page Admin > Paramètres ne montre pas la catégorie « interne ».
            $setting = (new AppSetting())
                ->setKey(self::HIDDEN_SETTING)
                ->setType(AppSetting::TYPE_STRING)
                ->setLabel('Avis Google masqués sur le site')
                ->setDescription('Identifiants Google des avis lus en direct que l\'admin a masqués (Admin > Avis Google).')
                ->setCategory('interne');
            $this->em->persist($setting);
        }
        $setting->setRawValue([] === $hidden ? null : json_encode($hidden));
        $this->em->flush();
        $this->settings->clearCache();

        return $nowHidden;
    }

    /** @return list<string> */
    private function getHiddenKeys(): array
    {
        $keys = json_decode($this->settings->getString(self::HIDDEN_SETTING, '[]'), true);

        return is_array($keys) ? array_values(array_filter($keys, 'is_string')) : [];
    }

    /** @return array{place: array|null, error: string|null} */
    private function readPlace(): array
    {
        if (null !== $this->read) {
            return $this->read;
        }

        $placeId = $this->getPlaceId();
        if ('' === trim($this->apiKey) || null === $placeId) {
            return $this->read = ['place' => null, 'error' => null];
        }

        return $this->read = $this->cache->get(self::cacheKey($placeId), function (ItemInterface $item) use ($placeId): array {
            $read = $this->fetch($placeId);
            $item->expiresAfter(null === $read['place'] ? self::TTL_FAILURE : self::TTL);

            return $read;
        });
    }

    /** @return array{place: array|null, error: string|null} */
    private function fetch(string $placeId): array
    {
        try {
            $response = $this->http->request('GET', self::ENDPOINT . rawurlencode($placeId), [
                'query'        => ['languageCode' => 'fr'],
                // On ne demande que ce qu'on affiche : Google facture selon les champs demandés.
                'headers'      => ['X-Goog-Api-Key' => $this->apiKey, 'X-Goog-FieldMask' => 'rating,userRatingCount,reviews'],
                'timeout'      => self::TIMEOUT,
                'max_duration' => self::TIMEOUT,
            ]);
            $status = $response->getStatusCode();
            if (200 === $status) {
                return ['place' => self::normalize($response->toArray()), 'error' => null];
            }

            $this->logger->warning('Avis Google : Google refuse de lire la fiche.', ['status' => $status, 'body' => mb_substr($response->getContent(false), 0, 500)]);
            $error = match ($status) {
                400, 404 => 'Google ne trouve pas la fiche : vérifiez l\'identifiant de la fiche (Admin > Paramètres > Avis Google).',
                401, 403 => 'Google refuse la clé : vérifiez qu\'elle est juste, et que « Places API (New) » et la facturation sont activées sur le projet Google Cloud.',
                429      => 'Google limite les appels pour l\'instant : nouvel essai dans dix minutes.',
                default  => 'Google ne répond pas correctement (erreur ' . $status . ') : nouvel essai dans dix minutes.',
            };
        } catch (ExceptionInterface $e) {
            // Réseau, délai dépassé ou réponse illisible.
            $this->logger->warning('Avis Google : Google ne répond pas.', ['exception' => $e]);
            $error = 'Google ne répond pas pour l\'instant : nouvel essai dans dix minutes.';
        }

        return ['place' => null, 'error' => $error];
    }

    /**
     * La réponse de Google réduite à ce qu'on affiche, au format des avis du site
     * (author_name, rating, relative_time, text, profile_photo_url), vérifiée champ par champ.
     * Pas de photo de profil : elle obligerait chaque visiteur à charger une image chez Google.
     */
    private static function normalize(array $body): array
    {
        $reviews = [];
        foreach (is_array($body['reviews'] ?? null) ? $body['reviews'] : [] as $review) {
            if (!is_array($review) || !is_string($review['name'] ?? null)) {
                continue;
            }
            // Le texte tel que l'auteur l'a écrit, plutôt qu'une traduction automatique.
            $text = $review['originalText']['text'] ?? $review['text']['text'] ?? '';
            $url  = $review['authorAttribution']['uri'] ?? null;
            $name = $review['authorAttribution']['displayName'] ?? null;
            $when = $review['relativePublishTimeDescription'] ?? null;
            $reviews[] = [
                'key'               => $review['name'],
                'author_name'       => is_string($name) && '' !== trim($name) ? trim($name) : 'Client Google',
                'author_url'        => is_string($url) && preg_match('#^https://\S+$#i', $url) ? $url : null,
                'rating'            => max(1, min(5, (int) ($review['rating'] ?? 5))),
                'relative_time'     => is_string($when) && '' !== trim($when) ? trim($when) : null,
                'text'              => is_string($text) ? trim($text) : '',
                'profile_photo_url' => null,
            ];
        }

        return [
            'rating'  => round((float) ($body['rating'] ?? 0), 1),
            'total'   => max(0, (int) ($body['userRatingCount'] ?? 0)),
            'reviews' => $reviews,
            'read_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ];
    }

    private static function cacheKey(string $placeId): string
    {
        return 'google_places.' . $placeId;
    }
}
