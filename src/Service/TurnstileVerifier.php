<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Vérifie côté serveur les tokens Cloudflare Turnstile.
 *
 * Doc officielle : https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
 *
 * Fonctionnement :
 * 1. Le widget JS de Cloudflare met un token dans le champ caché `cf-turnstile-response`
 * 2. À la soumission du form, on récupère ce token côté serveur
 * 3. On l'envoie à l'API siteverify de Cloudflare avec notre secret key
 * 4. Cloudflare répond { success: true|false, ... }
 *
 * Si la TURNSTILE_SECRET_KEY n'est pas configurée (env vide), la vérification
 * est skippée (mode dégradé : on s'en remet au honeypot + rate limiter).
 */
class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $secretKey,
    ) {
    }

    /**
     * Retourne true si Turnstile valide le token (ou si Turnstile est désactivé).
     *
     * @param string|null $token    Le token retourné par le widget côté navigateur
     * @param string|null $remoteIp L'IP du client (pour cross-check Cloudflare)
     */
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        // Mode "dégradé" : si pas de clé secrète configurée, on laisse passer
        // (le honeypot + rate limiter restent actifs en backup).
        if ($this->secretKey === '') {
            return true;
        }

        // Token absent → bot ou erreur JS → on refuse
        if (!$token) {
            $this->logger->info('[Turnstile] Token absent — rejet');
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => array_filter([
                    'secret'   => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ], static fn ($v) => $v !== null && $v !== ''),
                'timeout' => 5, // Cloudflare répond en <100ms en général
            ]);

            $data = $response->toArray(false);
            $success = (bool) ($data['success'] ?? false);

            if (!$success) {
                $this->logger->info('[Turnstile] Vérification échouée', [
                    'errors' => $data['error-codes'] ?? [],
                    'ip'     => $remoteIp,
                ]);
            }

            return $success;

        } catch (\Throwable $e) {
            // Si Cloudflare est down / timeout → fail-open par défaut pour ne pas
            // bloquer le formulaire. Le honeypot + rate limiter restent actifs.
            // Si tu préfères du fail-closed, change `return true` en `return false`.
            $this->logger->error('[Turnstile] Erreur API Cloudflare : ' . $e->getMessage());
            return true;
        }
    }
}
