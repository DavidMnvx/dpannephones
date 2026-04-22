<?php

namespace App\Twig;

use App\Repository\ReviewRepository;
use App\Repository\SiteImageRepository;
use App\Repository\SocialLinkRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose aux templates :
 *  - `admin_pending_reviews_count()` : compteur avis en attente (badge sidebar admin)
 *  - `site_image('slug')` : chemin web d'une image modifiable depuis l'admin
 *  - `social_links()` : liste des réseaux sociaux actifs (avec icône + couleur)
 */
class AdminExtension extends AbstractExtension
{
    private ?int $pendingCount = null;
    private array $imageCache = [];
    private ?array $socialLinksCache = null;

    public function __construct(
        private ReviewRepository $reviewRepo,
        private SiteImageRepository $siteImageRepo,
        private SocialLinkRepository $socialLinkRepo,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_pending_reviews_count', [$this, 'getPendingReviewsCount']),
            new TwigFunction('site_image', [$this, 'getSiteImage']),
            new TwigFunction('social_links', [$this, 'getSocialLinks']),
        ];
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
