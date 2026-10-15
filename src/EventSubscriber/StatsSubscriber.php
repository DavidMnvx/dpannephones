<?php

namespace App\EventSubscriber;

use App\Entity\DailyStat;
use App\Repository\DailyStatRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Statistiques de fréquentation maison — sans cookie dédié, sans adresse IP.
 *
 * - kernel.response : on décide quoi compter (route, robots, admin) et on pose le
 *   marqueur « visite du jour » en session. Ça ne peut pas attendre terminate :
 *   à ce stade la session est déjà enregistrée, le marqueur serait perdu.
 * - kernel.terminate : l'écriture SQL, une fois la réponse partie chez le visiteur.
 *
 * Les commandes ne se comptent pas ici : elles se lisent dans la table commande.
 */
class StatsSubscriber implements EventSubscriberInterface
{
    private const ATTRIBUT = '_stats_a_compter';
    private const SESSION_VISITE = '_stats_visite_boutique';

    private const ROBOTS = '/bot|crawl|spider|curl|wget|python|headless|monitor|uptime|facebookexternalhit|scan|probe|go-http|java|libwww|ahrefs|semrush|petal|bytespider|gpt|claude|dataprovider|zgrab|census|lighthouse|pingdom|preview/i';

    public function __construct(
        private DailyStatRepository $stats,
        private Security $security,
        private LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE  => 'onResponse',
            KernelEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || $event->getResponse()->getStatusCode() >= 400) {
            return;
        }

        $request  = $event->getRequest();
        $response = $event->getResponse();
        $route    = $request->attributes->get('_route');
        if (!in_array($route, ['boutique_index', 'boutique_show', 'cart_add', 'payment_checkout'], true)
            || $this->estUnRobot($request)
            || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $aCompter = match ($route) {
            'boutique_index'   => $this->premiereVisiteDuJour($request) ? [DailyStat::VISITE_BOUTIQUE, ''] : null,
            'boutique_show'    => [DailyStat::VUE_FICHE, (string) $request->attributes->get('id')],
            'cart_add'         => [DailyStat::AJOUT_PANIER, ''],
            // Seul le départ vers Stripe compte : les autres redirections renvoient
            // au panier (vide) ou à l'étape livraison (mode non choisi)
            'payment_checkout' => $this->partVersLExterieur($request, $response) ? [DailyStat::CHECKOUT, ''] : null,
        };

        if ($aCompter !== null) {
            $request->attributes->set(self::ATTRIBUT, $aCompter);
        }
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $aCompter = $event->getRequest()->attributes->get(self::ATTRIBUT);
        if ($aCompter === null) {
            return;
        }

        // Une statistique ne doit jamais casser quoi que ce soit : on journalise et on passe.
        try {
            $this->stats->increment(...$aCompter);
        } catch (\Throwable $e) {
            $this->logger->warning('Statistiques : incrément impossible', ['exception' => $e]);
        }
    }

    private function partVersLExterieur(Request $request, Response $response): bool
    {
        $hote = parse_url((string) $response->headers->get('Location'), PHP_URL_HOST);

        return $response->isRedirection() && is_string($hote) && $hote !== $request->getHost();
    }

    private function estUnRobot(Request $request): bool
    {
        // Préchargements (Turbo au survol, navigateurs) : pas une vraie visite
        foreach (['Sec-Purpose', 'X-Sec-Purpose', 'Purpose', 'X-Purpose'] as $entete) {
            if (str_contains(strtolower((string) $request->headers->get($entete)), 'prefetch')) {
                return true;
            }
        }

        $ua = (string) $request->headers->get('User-Agent');

        return $ua === '' || preg_match(self::ROBOTS, $ua) === 1;
    }

    /** Un visiteur = une session par jour : la date du jour est notée dans la session. */
    private function premiereVisiteDuJour(Request $request): bool
    {
        if (!$request->hasSession()) {
            return false;
        }

        $session = $request->getSession();
        $jour    = DailyStatRepository::aujourdhui()->format('Y-m-d');
        if ($session->get(self::SESSION_VISITE) === $jour) {
            return false;
        }

        $session->set(self::SESSION_VISITE, $jour);

        return true;
    }
}
