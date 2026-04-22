<?php

namespace App\EventSubscriber;

use App\Service\AppSettingService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Mode maintenance global du site.
 *
 * Si la clé AppSetting "site_maintenance_mode" est à true :
 *  - Tous les visiteurs non-admin sont redirigés vers /login
 *  - L'admin connecté (ROLE_ADMIN) continue d'accéder à tout le site
 *
 * Routes jamais bloquées (même en maintenance) :
 *  - login / logout / verify_email / reset-password (sinon admin ne peut plus se connecter)
 *  - /admin/* (géré par la sécurité Symfony — ROLE_ADMIN requis de toute façon)
 *  - /payment/webhook (webhook Stripe — ne JAMAIS casser les paiements)
 *  - Profiler / WDT (en dev)
 *  - Assets statiques (déjà servis directement par Nginx en prod)
 */
class MaintenanceSubscriber implements EventSubscriberInterface
{
    /**
     * Prefixes de chemin toujours accessibles.
     */
    private const ALWAYS_ALLOWED_PATH_PREFIXES = [
        '/login',
        '/logout',
        '/verify',
        '/reset-password',
        '/resend',
        '/admin',              // Géré par le firewall Symfony
        '/payment/webhook',    // Webhook Stripe ne doit JAMAIS être bloqué
        '/_wdt',               // Profiler / Web Debug Toolbar
        '/_profiler',
        '/_error',
        '/assets',
        '/uploads',
        '/images',
        '/build',
        '/favicon.ico',
        '/robots.txt',
        '/sitemap.xml',
    ];

    public function __construct(
        private AppSettingService $settings,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Priorité 10 : après le firewall de sécurité (qui tourne à ~8) pour avoir $security->getUser()
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    /**
     * Pages désactivables individuellement : préfixe de chemin → clé AppSetting.
     * Si le setting vaut `false` → la page redirige vers l'accueil avec un message.
     *
     * L'admin connecté continue de voir la page normalement (pour pouvoir la préparer).
     */
    private const PAGE_TOGGLES = [
        '/boutique'         => 'page_boutique_enabled',
        '/reparations'      => 'page_reparations_enabled',
        '/ordinateur'       => 'page_ordinateur_enabled',
        '/partenaires'      => 'page_partenaires_enabled',
        '/reviews'          => 'page_reviews_enabled',
        '/contact'          => 'page_contact_enabled',
        '/qui-sommes-nous'  => 'page_about_enabled',
    ];

    public function onKernelRequest(RequestEvent $event): void
    {
        // Ignorer les sous-requêtes (forward, render_esi…)
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path    = $request->getPathInfo();

        // Routes toujours autorisées (quelles que soient les options)
        foreach (self::ALWAYS_ALLOWED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        // Admin connecté → accès total (il peut préparer le catalogue / voir les pages off)
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        // ── 1. Mode maintenance global (priorité) ─────────────
        if ($this->settings->getBool('site_maintenance_mode', false) && !$isAdmin) {
            $request->getSession()?->getFlashBag()->add(
                'info',
                '🚧 Le site est en cours de préparation. Connectez-vous avec un compte administrateur pour y accéder, ou revenez nous voir très bientôt !'
            );
            $response = new RedirectResponse(
                $this->urlGenerator->generate('app_login')
            );
            $event->setResponse($response);
            return;
        }

        // ── 2. Désactivation d'une page spécifique ────────────
        if (!$isAdmin) {
            foreach (self::PAGE_TOGGLES as $prefix => $settingKey) {
                if (str_starts_with($path, $prefix)) {
                    // Par défaut la page est activée (true) — le toggle désactive
                    $enabled = $this->settings->getBool($settingKey, true);
                    if (!$enabled) {
                        $request->getSession()?->getFlashBag()->add(
                            'info',
                            '🔒 Cette page est temporairement indisponible. Merci de votre compréhension — revenez bientôt !'
                        );
                        $response = new RedirectResponse(
                            $this->urlGenerator->generate('home')
                        );
                        $event->setResponse($response);
                        return;
                    }
                    break; // un seul préfixe peut matcher
                }
            }
        }
    }
}
