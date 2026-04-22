<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Ajoute des headers de sécurité à toutes les réponses HTTP.
 *
 * Ces headers protègent contre :
 *  - Clickjacking (X-Frame-Options)
 *  - MIME-sniffing (X-Content-Type-Options)
 *  - XSS réfléchi (X-XSS-Protection, déprécié mais utile pour vieux navigateurs)
 *  - Transport non-HTTPS (Strict-Transport-Security, actif uniquement en prod)
 *  - Données de référent fuyant (Referrer-Policy)
 *  - Permissions (caméra, micro… refusés par défaut)
 */
#[AsEventListener(event: 'kernel.response', priority: -10)]
class SecurityHeadersListener
{
    public function __construct(private string $environment) {}

    public function __invoke(ResponseEvent $event): void
    {
        // Seulement sur la requête principale (pas les sous-requêtes)
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers  = $response->headers;

        // Anti-clickjacking (empêche que le site soit iframé)
        $headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Empêche le navigateur de deviner le type MIME (sécurité XSS via fichiers uploadés)
        $headers->set('X-Content-Type-Options', 'nosniff');

        // Contrôle sur les informations de référent (n'envoie pas le chemin exact vers sites tiers)
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Refuse les permissions par défaut (caméra, micro, géoloc, paiement Apple Pay géré par Stripe)
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        // HSTS : force HTTPS pendant 1 an — uniquement en prod (casserait le dev local HTTP)
        if ($this->environment === 'prod') {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Note : on n'ajoute PAS de Content-Security-Policy strict ici pour l'instant —
        // les inline <script> et styles du site le casseraient. À ajouter plus tard
        // avec un nonce ou après audit des ressources externes.
    }
}
