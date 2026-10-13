<?php

namespace App\Tests\Service;

use App\Entity\GoogleReview;
use App\Repository\AppSettingRepository;
use App\Repository\GoogleReviewRepository;
use App\Service\AppSettingService;
use App\Service\GooglePlacesService;
use App\Service\StaticReviewsProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Avis Google en direct (GooglePlacesService) et ce que le site en affiche (StaticReviewsProvider) :
 * lecture de la fiche (Google simulé, aucun appel réseau), cache d'une heure, avis masqués,
 * et le secours (avis saisis dans l'admin) quand le direct ne marche pas.
 */
class GooglePlacesServiceTest extends TestCase
{
    /** @var list<array{url: string, headers: list<string>}> les appels reçus par le faux Google */
    private array $calls = [];

    public function testSansCleGoogleNEstPasAppeleEtLeSiteGardeLesAvisSaisis(): void
    {
        [$google, $provider] = $this->build(apiKey: '');

        self::assertSame(GooglePlacesService::STATE_NO_KEY, $google->getState());
        self::assertNull($google->getPlace());
        self::assertSame(['Saisi dans l\'admin'], array_column($provider->getReviews(), 'text'));
        self::assertSame([], $this->calls);
    }

    public function testUnIdentifiantDeFicheInvalideCoupeLeDirect(): void
    {
        [$google] = $this->build(settings: ['google_place_id' => 'pas une fiche']);

        self::assertSame(GooglePlacesService::STATE_NO_PLACE, $google->getState());
        self::assertSame([], $this->calls);
    }

    public function testEnDirectLaNoteEtLesAvisViennentDeGoogle(): void
    {
        [$google, $provider] = $this->build();

        self::assertSame(GooglePlacesService::STATE_LIVE, $google->getState());
        self::assertSame(4.8, $provider->getOverallRating());
        self::assertSame(52, $provider->getTotalRatings());
        // L'avis sans commentaire ne s'affiche pas ; le texte est celui de l'auteur, pas la traduction.
        self::assertSame(['Écran changé en 30 minutes.', 'Very good service.'], array_column($provider->getReviews(), 'text'));

        $reviews = $google->getPlace()['reviews'];
        self::assertCount(3, $reviews, 'l\'admin voit aussi l\'avis sans commentaire');
        self::assertSame('Marie L.', $reviews[0]['author_name']);
        self::assertSame('https://www.google.com/maps/contrib/1', $reviews[0]['author_url']);
        self::assertSame('il y a 2 semaines', $reviews[0]['relative_time']);
        self::assertNull($reviews[1]['author_url'], 'seul un lien https passe');
        self::assertSame(1, $reviews[1]['rating'], 'la note est ramenée entre 1 et 5');
        self::assertSame('Client Google', $reviews[2]['author_name']);

        // L'appel : la bonne fiche, en français, la clé dans l'en-tête, seulement les champs affichés.
        self::assertCount(1, $this->calls);
        self::assertSame('https://places.googleapis.com/v1/places/' . GooglePlacesService::DEFAULT_PLACE_ID . '?languageCode=fr', $this->calls[0]['url']);
        self::assertContains('X-Goog-Api-Key: cle-de-test', $this->calls[0]['headers']);
        self::assertContains('X-Goog-FieldMask: rating,userRatingCount,reviews', $this->calls[0]['headers']);
    }

    public function testGoogleEstLuUneFoisParHeureSaufSiOnActualise(): void
    {
        $cache = new ArrayAdapter();
        $this->build(cache: $cache)[1]->getReviews();
        $this->build(cache: $cache)[1]->getOverallRating();
        self::assertCount(1, $this->calls, 'le second visiteur lit le cache');

        [$google] = $this->build(cache: $cache);
        $google->refresh();
        $google->getPlace();
        self::assertCount(2, $this->calls);
    }

    public function testUnAvisMasqueNeSAffichePlusEtToutMasquerRameneLesAvisSaisis(): void
    {
        $un = 'places/x/reviews/un';
        [$google, $provider] = $this->build(settings: [GooglePlacesService::HIDDEN_SETTING => json_encode([$un])]);
        self::assertSame(['Very good service.'], array_column($provider->getReviews(), 'text'));
        self::assertTrue($google->getPlace()['reviews'][0]['hidden']);

        [, $provider] = $this->build(settings: [GooglePlacesService::HIDDEN_SETTING => json_encode([$un, 'places/x/reviews/deux'])]);
        self::assertSame(['Saisi dans l\'admin'], array_column($provider->getReviews(), 'text'));
        self::assertSame(4.8, $provider->getOverallRating(), 'la note reste celle de Google');
    }

    public function testOnNeMasqueQuUnAvisDeLaFiche(): void
    {
        [$google] = $this->build();

        self::assertNull($google->toggleHidden('places/autre/reviews/x'));
    }

    public function testSiGoogleRefuseLeSiteRetombeSurLesAvisSaisisEtLAdminSaitPourquoi(): void
    {
        [$google, $provider] = $this->build(response: new MockResponse('{"error":{"code":403}}', ['http_code' => 403]));
        self::assertSame(GooglePlacesService::STATE_UNREACHABLE, $google->getState());
        self::assertStringContainsString('Google refuse la clé', (string) $google->getError());
        self::assertSame(['Saisi dans l\'admin'], array_column($provider->getReviews(), 'text'));

        [$google] = $this->build(response: new MockResponse('', ['error' => 'délai dépassé']));
        self::assertStringContainsString('Google ne répond pas', (string) $google->getError());
    }

    /**
     * @param array<string, string> $settings
     *
     * @return array{GooglePlacesService, StaticReviewsProvider}
     */
    private function build(string $apiKey = 'cle-de-test', array $settings = [], ?MockResponse $response = null, ?ArrayAdapter $cache = null): array
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->calls[] = ['url' => $url, 'headers' => $options['headers']];

            return $response ?? new MockResponse(json_encode(self::PLACE));
        });

        $appSettings = $this->createStub(AppSettingService::class);
        $appSettings->method('getString')->willReturnCallback(fn (string $key, string $default = '') => $settings[$key] ?? $default);

        $google = new GooglePlacesService(
            $http,
            $cache ?? new ArrayAdapter(),
            $appSettings,
            $this->createStub(AppSettingRepository::class),
            $this->createStub(EntityManagerInterface::class),
            new NullLogger(),
            $apiKey,
        );

        $saisi = (new GoogleReview())->setAuthorName('Paul')->setRating(5)->setText('Saisi dans l\'admin');
        $repository = $this->createStub(GoogleReviewRepository::class);
        $repository->method('findActiveOrdered')->willReturn([$saisi]);
        // Un dossier sans config/google_reviews.php : le fichier de secours n'intervient pas ici.
        $provider = new StaticReviewsProvider($repository, sys_get_temp_dir(), $google);

        return [$google, $provider];
    }

    private const PLACE = [
        'rating' => 4.8,
        'userRatingCount' => 52,
        'reviews' => [
            [
                'name' => 'places/x/reviews/un',
                'relativePublishTimeDescription' => 'il y a 2 semaines',
                'rating' => 5,
                'originalText' => ['text' => 'Écran changé en 30 minutes.'],
                'authorAttribution' => ['displayName' => 'Marie L.', 'uri' => 'https://www.google.com/maps/contrib/1'],
            ],
            [
                'name' => 'places/x/reviews/deux',
                'rating' => 0,
                'text' => ['text' => 'Très bon service.'],
                'originalText' => ['text' => 'Very good service.'],
                'authorAttribution' => ['displayName' => 'John', 'uri' => 'javascript:alert(1)'],
            ],
            [
                'name' => 'places/x/reviews/trois',
                'rating' => 5,
            ],
            ['rating' => 5, 'text' => ['text' => 'Sans identifiant : ignoré.']],
        ],
    ];
}
