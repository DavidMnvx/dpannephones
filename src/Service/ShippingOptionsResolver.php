<?php

namespace App\Service;

/**
 * Résout les options de livraison disponibles selon le contenu du panier.
 *
 * LOGIQUE :
 * Chaque catégorie a un "tier" (niveau d'exigence de livraison, 1 = petit colis, 4 = PC volumineux).
 * Le tier le plus ÉLEVÉ du panier détermine les options disponibles — les autres articles
 * voyagent dans le même colis (le colis sécurisé englobe tout).
 *
 * Exemple : panier = hydrogel (T1) + accessoire (T1) + PC Gamer (T4) → on applique le tier 4,
 * seules les options Chronopost XL / UPS / Retrait sont proposées (le tout voyage dans le colis PC).
 */
class ShippingOptionsResolver
{
    public function __construct(private \App\Repository\CategoryRepository $categoryRepo)
    {
    }

    /** Tier d'une catégorie : base de données d'abord, constantes historiques en secours */
    private function tierOf(string $cat): int
    {
        return $this->categoryRepo->getSlugTierMap()[$cat]
            ?? self::CATEGORY_TIERS[$cat]
            ?? 1;
    }

    /**
     * Niveau d'exigence de livraison par catégorie.
     * Plus c'est haut, plus le colis doit être sécurisé / volumineux.
     */
    public const CATEGORY_TIERS = [
        'accessoires'    => 1,  // Petit, léger — lettre suivie possible
        'film_hydrogel'  => 1,
        'telephone'      => 2,  // Valeur moyenne — colis suivi obligatoire
        'pc_portable'    => 3,  // Volumineux et fragile
        'pc_portable_occasion' => 3,
        'pc_bureautique' => 4,  // Très volumineux — transporteur spécialisé
        'pc_gamer'       => 4,
        'pc_gamer_occasion'    => 4,
    ];

    /**
     * Options autorisées par tier.
     * Un article de tier N peut être livré via n'importe quelle option de son tier.
     * Les articles de tier inférieur peuvent voyager dans le même colis que l'article de tier max.
     */
    public const TIER_OPTIONS = [
        1 => ['retrait', 'courrier', 'mondial_relay', 'colissimo', 'chronopost'],
        2 => ['retrait', 'mondial_relay', 'colissimo', 'chronopost'],
        3 => ['retrait', 'colissimo', 'chronopost', 'chronopost_xl'],
        4 => ['retrait', 'chronopost_xl', 'ups'],
    ];

    /**
     * Configuration des modes de livraison.
     *
     * - label    : libellé visible
     * - price    : prix TTC (0 = gratuit)
     * - delay    : texte du délai estimé
     * - icon     : classe FontAwesome
     * - color    : couleur de l'icône (hex)
     * - carrier  : nom transporteur (repris sur la commande, email, suivi)
     */
    public const OPTIONS = [
        'retrait' => [
            'label'      => 'Retrait en boutique',
            'price'      => 0.00,
            'delay'      => 'Gratuit · sous 24-48h',
            'icon'       => 'fas fa-store',
            'color'      => '#16a34a',
            'carrier'    => 'Retrait boutique',
            'description'=> 'Pélissanne · D\'panne Phones',
        ],
        'courrier' => [
            'label'      => 'Lettre suivie',
            'price'      => 2.90,
            'delay'      => '3-5 jours ouvrés',
            'icon'       => 'fas fa-envelope',
            'color'      => '#3b82f6',
            'carrier'    => 'La Poste — Lettre suivie',
            'description'=> 'Pour petits articles légers',
        ],
        'mondial_relay' => [
            'label'      => 'Mondial Relay',
            'price'      => 4.90,
            'delay'      => '3-5 jours · point relais',
            'icon'       => 'fas fa-map-marker-alt',
            'color'      => '#e8732a',
            'carrier'    => 'Mondial Relay',
            'description'=> 'Retrait en point relais proche de chez vous',
        ],
        'colissimo' => [
            'label'      => 'Colissimo',
            'price'      => 5.90,
            'delay'      => '2-3 jours ouvrés',
            'icon'       => 'fas fa-shipping-fast',
            'color'      => '#ffb700',
            'carrier'    => 'Colissimo',
            'description'=> 'Livraison à domicile, suivi inclus',
        ],
        'chronopost' => [
            'label'      => 'Chronopost Express',
            'price'      => 9.90,
            'delay'      => 'Express 24h ouvrés',
            'icon'       => 'fas fa-bolt',
            'color'      => '#7a2c8e',
            'carrier'    => 'Chronopost',
            'description'=> 'Livraison express en 24h',
        ],
        'chronopost_xl' => [
            'label'      => 'Chronopost XL',
            'price'      => 14.90,
            'delay'      => '24-48h ouvrés',
            'icon'       => 'fas fa-truck',
            'color'      => '#7a2c8e',
            'carrier'    => 'Chronopost XL',
            'description'=> 'Colis volumineux (PC, écrans…)',
        ],
        'ups' => [
            'label'      => 'UPS Standard',
            'price'      => 16.90,
            'delay'      => '2-4 jours ouvrés',
            'icon'       => 'fas fa-truck-moving',
            'color'      => '#8b4513',
            'carrier'    => 'UPS',
            'description'=> 'Livraison sécurisée colis volumineux',
        ],
    ];

    /**
     * Retourne le tier le plus exigeant (max) parmi les catégories du panier.
     * Défaut : 1 (petit colis) si panier vide ou catégorie inconnue.
     */
    public function getMaxTier(array $cartCategories): int
    {
        if (empty($cartCategories)) {
            return 1;
        }

        $maxTier = 1;
        foreach ($cartCategories as $cat) {
            $tier = $this->tierOf($cat);
            if ($tier > $maxTier) {
                $maxTier = $tier;
            }
        }

        return $maxTier;
    }

    /**
     * Retourne les options de livraison disponibles pour un panier donné.
     * On applique les options du tier MAX du panier (tout voyage dans le colis le plus sécurisé).
     *
     * @param array $cartCategories Liste des catégories présentes dans le panier
     * @return array<string, array> Les options compatibles indexées par code
     */
    public function getAvailableOptions(array $cartCategories): array
    {
        $cartCategories = array_filter(array_unique($cartCategories));
        $maxTier        = $this->getMaxTier($cartCategories);
        $allowedCodes   = self::TIER_OPTIONS[$maxTier] ?? [];

        $available = [];
        foreach ($allowedCodes as $code) {
            if (isset(self::OPTIONS[$code])) {
                $available[$code] = self::OPTIONS[$code];
            }
        }

        return $available;
    }

    /**
     * Retourne les infos d'une option par son code (ou null).
     */
    public function getOption(string $code): ?array
    {
        return self::OPTIONS[$code] ?? null;
    }

    /**
     * Retourne le coût d'une option (0 si code invalide).
     */
    public function getCost(string $code): float
    {
        return (float) (self::OPTIONS[$code]['price'] ?? 0);
    }

    /**
     * Retourne le nom du transporteur pour une option (pour sauvegarde commande).
     */
    public function getCarrierName(string $code): string
    {
        return self::OPTIONS[$code]['carrier'] ?? 'Inconnu';
    }

    /**
     * Retourne le code du mode par défaut valide pour le panier.
     * Priorité : retrait > colissimo > premier dispo.
     */
    public function getDefaultOption(array $cartCategories): string
    {
        $available = $this->getAvailableOptions($cartCategories);

        if (isset($available['retrait'])) {
            return 'retrait';
        }
        if (isset($available['colissimo'])) {
            return 'colissimo';
        }

        return array_key_first($available) ?? 'retrait';
    }

    /**
     * Indique si le panier contient un article "volumineux" qui impose des restrictions
     * (tier >= 3 = PC portable, gamer, bureautique).
     * Utilisé dans l'UI pour afficher un message explicatif.
     */
    public function hasBulkyItem(array $cartCategories): bool
    {
        return $this->getMaxTier($cartCategories) >= 3;
    }

    /**
     * Retourne le label de la catégorie la plus exigeante (pour message UI).
     */
    public function getBulkyLabel(array $cartCategories): ?string
    {
        $maxTier = $this->getMaxTier($cartCategories);

        if ($maxTier < 3) {
            return null;
        }

        $labels = $this->categoryRepo->getSlugLabelMap();

        foreach ($cartCategories as $cat) {
            if (isset($labels[$cat]) && $this->tierOf($cat) === $maxTier) {
                return $labels[$cat];
            }
        }

        return 'un article volumineux';
    }

    /**
     * Extrait les catégories uniques d'un panier (Collection de CartItem).
     *
     * @param iterable $cartItems
     * @return string[]
     */
    public static function extractCategories(iterable $cartItems): array
    {
        $cats = [];
        foreach ($cartItems as $item) {
            $cat = $item->getArticle()->getCategorie();
            if ($cat) {
                $cats[] = $cat;
            }
        }

        return array_values(array_unique($cats));
    }
}
