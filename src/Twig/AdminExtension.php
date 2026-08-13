<?php

namespace App\Twig;

use App\Repository\ReviewRepository;
use App\Repository\SiteImageRepository;
use App\Repository\SocialLinkRepository;
use App\Service\AppSettingService;
use App\Service\StaticReviewsProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
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
        private \App\Repository\ProductColorRepository $productColorRepo,
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
            new TwigFunction('product_color', [$this, 'getProductColor']),
        ];
    }

    /**
     * Couleur produit par id — permet aux templates d'afficher la couleur de
     * la photo principale (specs.main_photo_color_id) même quand aucune autre
     * photo ne porte ce coloris.
     */
    public function getProductColor(int|string|null $id): ?\App\Entity\ProductColor
    {
        return $id ? $this->productColorRepo->find((int) $id) : null;
    }

    /**
     * Lit un paramètre global texte (AppSetting) — ex : accroche boutique par catégorie.
     */
    public function getAppSetting(string $key, string $default = ''): string
    {
        return $this->settings->getString($key, $default);
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('rich_description', [$this, 'formatRichDescription'], ['is_safe' => ['html']]),
            new TwigFilter('blog_content', [$this, 'formatBlogContent'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Rendu du contenu des articles « Conseils & Actualités ».
     *
     * Même philosophie que rich_description (texte structuré, HTML échappé),
     * avec en plus : « ## Mon sous-titre » => <h2> explicite, et les liens
     * markdown « [texte](url) » => <a> (externe = nouvel onglet). Les liens
     * internes (produits, catégories, services) restent dans l'onglet.
     */
    public function formatBlogContent(?string $text): string
    {
        if (!$text || trim($text) === '') {
            return '';
        }

        $linkify = function (string $escaped): string {
            return preg_replace_callback(
                '/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/u',
                function ($m) {
                    $href = htmlspecialchars($m[2], ENT_QUOTES);
                    $ext  = str_starts_with($m[2], 'http') && !str_contains($m[2], 'dpannephones.fr');
                    $attr = $ext ? ' target="_blank" rel="noopener"' : '';
                    return '<a href="' . $href . '"' . $attr . '>' . $m[1] . '</a>';
                },
                $escaped
            );
        };

        $blocks  = [];
        $current = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($current) { $blocks[] = $current; $current = []; }
            } else {
                $current[] = $line;
            }
        }
        if ($current) {
            $blocks[] = $current;
        }

        $html = '';
        foreach ($blocks as $block) {
            // Sous-titres explicites : chaque ligne "## ..." du bloc devient un h2
            if (preg_match('/^##\s+/u', $block[0])) {
                foreach ($block as $l) {
                    if (preg_match('/^##\s+(.+)$/u', $l, $m)) {
                        $html .= '<h2>' . htmlspecialchars($m[1], ENT_QUOTES) . '</h2>';
                    } else {
                        $html .= '<p>' . $linkify(htmlspecialchars($l, ENT_QUOTES)) . '</p>';
                    }
                }
                continue;
            }

            $isList = count(array_filter($block, fn ($l) => preg_match('/^[\*\-•]\s+/u', $l))) === count($block);

            if ($isList) {
                $html .= '<ul>';
                foreach ($block as $l) {
                    $html .= '<li>' . $linkify(htmlspecialchars(preg_replace('/^[\*\-•]\s+/u', '', $l), ENT_QUOTES)) . '</li>';
                }
                $html .= '</ul>';
            } elseif (count($block) === 1 && mb_strlen($block[0]) <= 65
                      && !preg_match('/[.!?;:,]$/u', $block[0]) && !str_contains($block[0], '[')) {
                $html .= '<h2>' . htmlspecialchars($block[0], ENT_QUOTES) . '</h2>';
            } else {
                $escaped = array_map(fn ($l) => $linkify(htmlspecialchars($l, ENT_QUOTES)), $block);
                $html .= '<p>' . implode('<br>', $escaped) . '</p>';
            }
        }

        return $html;
    }

    /**
     * Met en forme une description saisie en texte libre dans l'admin :
     *  - lignes commençant par "*", "-" ou "•"  → liste à puces
     *  - ligne courte isolée sans ponctuation finale → sous-titre
     *  - le reste → paragraphes (retours à la ligne préservés)
     * Le contenu est échappé : aucune balise saisie n'est interprétée.
     */
    public function formatRichDescription(?string $text): string
    {
        if (!$text || trim($text) === '') {
            return '';
        }

        // Découpage en blocs séparés par des lignes vides
        $blocks  = [];
        $current = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($current) { $blocks[] = $current; $current = []; }
            } else {
                $current[] = $line;
            }
        }
        if ($current) {
            $blocks[] = $current;
        }

        $html = '';
        foreach ($blocks as $block) {
            $isList = count(array_filter($block, fn ($l) => preg_match('/^[\*\-•]\s+/u', $l))) === count($block);

            if ($isList) {
                $html .= '<ul>';
                foreach ($block as $l) {
                    $html .= '<li>' . htmlspecialchars(preg_replace('/^[\*\-•]\s+/u', '', $l), ENT_QUOTES) . '</li>';
                }
                $html .= '</ul>';
            } elseif (count($block) === 1 && mb_strlen($block[0]) <= 65 && !preg_match('/[.!?;:,]$/u', $block[0])) {
                $html .= '<h4>' . htmlspecialchars($block[0], ENT_QUOTES) . '</h4>';
            } else {
                $escaped = array_map(fn ($l) => htmlspecialchars($l, ENT_QUOTES), $block);
                $html .= '<p>' . implode('<br>', $escaped) . '</p>';
            }
        }

        return $html;
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
