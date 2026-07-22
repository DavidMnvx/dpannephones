<?php

namespace App\Twig;

use App\Repository\ReviewRepository;
use App\Repository\SiteImageRepository;
use App\Repository\SocialLinkRepository;
use App\Service\AppSettingService;
use App\Service\StaticReviewsProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose aux templates :
 *  - `admin_pending_reviews_count()` : compteur avis en attente (badge sidebar admin)
 *  - `site_image('slug')` : chemin web d'une image modifiable depuis l'admin
 *  - `social_links()` : liste des réseaux sociaux actifs (avec icône + couleur)
 *  - `page_enabled('boutique')` : true/false selon le toggle AppSetting `page_boutique_enabled`
 *  - `google_rating()` : note moyenne Google (float|null)
 *  - `google_reviews_count()` : nombre total d'avis Google (int|null)
 */
class AdminExtension extends AbstractExtension
{
    private ?int $pendingCount = null;
    private array $imageCache = [];
    private ?array $socialLinksCache = null;
    private array $pageEnabledCache = [];

    public function __construct(
        private ReviewRepository $reviewRepo,
        private SiteImageRepository $siteImageRepo,
        private SocialLinkRepository $socialLinkRepo,
        private AppSettingService $settings,
        private StaticReviewsProvider $reviewsProvider,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_pending_reviews_count', [$this, 'getPendingReviewsCount']),
            new TwigFunction('site_image', [$this, 'getSiteImage']),
            new TwigFunction('social_links', [$this, 'getSocialLinks']),
            new TwigFunction('page_enabled', [$this, 'isPageEnabled']),
            new TwigFunction('google_rating', [$this, 'getGoogleRating']),
            new TwigFunction('google_reviews_count', [$this, 'getGoogleReviewsCount']),
            new TwigFunction('app_setting', [$this, 'getAppSetting']),
        ];
    }

    /**
     * Lit un paramètre global texte (AppSetting) — ex : accroche boutique par catégorie.
     */
    public function getAppSetting(string $key, string $default = ''): string
    {
        return $this->settings->getString($key, $default);
    }

    /**
     * Vérifie si une page publique est activée (toggle admin /admin/parametres).
     * Par défaut TRUE (on n'interdit une page que si elle est explicitement désactivée).
     */
    public function isPageEnabled(string $page): bool
    {
        if (!isset($this->pageEnabledCache[$page])) {
            $this->pageEnabledCache[$page] = $this->settings->getBool("page_{$page}_enabled", true);
        }
        return $this->pageEnabledCache[$page];
    }

    public function getGoogleRating(): ?float
    {
        return $this->reviewsProvider->getOverallRating();
    }

    public function getGoogleReviewsCount(): ?int
    {
        return $this->reviewsProvider->getTotalRatings();
    }

    /**
     * Retourne les réseaux sociaux actifs triés (cache en mémoire par requête).
     *
     * @return \App\Entity\SocialLink[]
     */
    public function getSocialLinks(): array
    {
        if ($this->socialLinksCache === null) {
            $this->socialLinksCache = $this->socialLinkRepo->findActiveOrdered();
        }
        return $this->socialLinksCache;
    }

    /**
     * Cache en mémoire pour éviter plusieurs requêtes par page.
     */
    public function getPendingReviewsCount(): int
    {
        if ($this->pendingCount === null) {
            $this->pendingCount = $this->reviewRepo->countPending();
        }

        return $this->pendingCount;
    }

    /**
     * Retourne le chemin web d'une SiteImage (custom si uploadée, sinon par défaut).
     * Ex: {{ site_image('home_shop_photo') }} → 'uploads/images/xxx.jpg' ou 'images/accueil/yyy.jpeg'
     *
     * @param string $slug Clé technique de l'image
     * @param string|null $fallback Image de secours si slug inconnu en base
     */
    public function getSiteImage(string $slug, ?string $fallback = null): ?string
    {
        if (!isset($this->imageCache[$slug])) {
            $img = $this->siteImageRepo->findBySlug($slug);
            $this->imageCache[$slug] = $img?->getWebPath() ?: $fallback;
        }

        return $this->imageCache[$slug];
    }
}
