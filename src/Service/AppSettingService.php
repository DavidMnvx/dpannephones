<?php

namespace App\Service;

use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;

/**
 * Façade d'accès typée aux paramètres globaux du site.
 *
 * Usage :
 *   $service->getBool('review_reward_enabled', false);
 *   $service->getFloat('review_reward_discount_value', 10.0);
 *
 * Cache en mémoire : une seule query pour toutes les lectures d'une requête HTTP.
 */
class AppSettingService
{
    private ?array $cache = null;

    public function __construct(
        private AppSettingRepository $repo,
    ) {}

    public function getBool(string $key, bool $default = false): bool
    {
        $setting = $this->getCached($key);
        if (!$setting) {
            return $default;
        }
        return (bool) $setting->getTypedValue();
    }

    public function getInt(string $key, int $default = 0): int
    {
        $setting = $this->getCached($key);
        if (!$setting) {
            return $default;
        }
        return (int) $setting->getTypedValue();
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $setting = $this->getCached($key);
        if (!$setting) {
            return $default;
        }
        return (float) $setting->getTypedValue();
    }

    public function getString(string $key, string $default = ''): string
    {
        $setting = $this->getCached($key);
        if (!$setting) {
            return $default;
        }
        return (string) $setting->getTypedValue();
    }

    /**
     * Retourne le setting brut (utile pour l'admin).
     */
    public function get(string $key): ?AppSetting
    {
        return $this->getCached($key);
    }

    /**
     * Invalide le cache (à appeler après modification en base).
     */
    public function clearCache(): void
    {
        $this->cache = null;
    }

    private function getCached(string $key): ?AppSetting
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach ($this->repo->findAll() as $setting) {
                $this->cache[$setting->getKey()] = $setting;
            }
        }
        return $this->cache[$key] ?? null;
    }
}
