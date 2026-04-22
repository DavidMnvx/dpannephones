<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\MarqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Génère dynamiquement le sitemap.xml à partir des pages du site
 * et des entités indexables (articles, marques).
 */
class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap', defaults: ['_format' => 'xml'])]
    public function sitemap(
        ArticleRepository $articleRepo,
        MarqueRepository $marqueRepo,
        EntityManagerInterface $em
    ): Response
    {
        $urls = [];
        $today = (new \DateTime())->format('Y-m-d');

        // ─── Pages statiques principales ───
        $staticRoutes = [
            'home'                 => ['priority' => 1.0,  'freq' => 'weekly'],
            'boutique_index'       => ['priority' => 0.9,  'freq' => 'daily'],
            'reparations_home'     => ['priority' => 0.9,  'freq' => 'weekly'],
            'contact'              => ['priority' => 0.6,  'freq' => 'monthly'],
            'about'                => ['priority' => 0.5,  'freq' => 'monthly'],
            'partenaires_index'    => ['priority' => 0.5,  'freq' => 'monthly'],
            'reviews_index'        => ['priority' => 0.7,  'freq' => 'weekly'],
            'legal_cgv'            => ['priority' => 0.3,  'freq' => 'yearly'],
            'legal_mentions'       => ['priority' => 0.3,  'freq' => 'yearly'],
            'legal_confidentialite'=> ['priority' => 0.3,  'freq' => 'yearly'],
        ];

        foreach ($staticRoutes as $routeName => $opts) {
            try {
                $urls[] = [
                    'loc'        => $this->generateUrl($routeName, [], 0),
                    'lastmod'    => $today,
                    'changefreq' => $opts['freq'],
                    'priority'   => $opts['priority'],
                ];
            } catch (\Exception $e) {
                // Route inexistante, on skip
            }
        }

        // ─── Pages catégories boutique ───
        $categories = ['telephone', 'pc_gamer', 'pc_bureautique', 'pc_portable', 'accessoires', 'film_hydrogel'];
        foreach ($categories as $cat) {
            try {
                $urls[] = [
                    'loc'        => $this->generateUrl('boutique_index', ['categorie' => $cat], 0),
                    'lastmod'    => $today,
                    'changefreq' => 'weekly',
                    'priority'   => 0.7,
                ];
            } catch (\Exception $e) {}
        }

        // ─── Articles de la boutique ───
        foreach ($articleRepo->findAll() as $article) {
            try {
                $urls[] = [
                    'loc'        => $this->generateUrl('boutique_show', ['id' => $article->getId()], 0),
                    'lastmod'    => $today,
                    'changefreq' => 'weekly',
                    'priority'   => 0.6,
                ];
            } catch (\Exception $e) {}
        }

        // ─── Pages marques (réparation) ───
        foreach (['phone', 'tablet'] as $type) {
            try {
                $urls[] = [
                    'loc'        => $this->generateUrl('choix_marque', ['type' => $type], 0),
                    'lastmod'    => $today,
                    'changefreq' => 'monthly',
                    'priority'   => 0.7,
                ];
            } catch (\Exception $e) {}
        }

        // Génération XML
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . "</loc>\n";
            $xml .= '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
            $xml .= '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
            $xml .= '    <priority>' . $url['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        $response = new Response($xml);
        $response->headers->set('Content-Type', 'application/xml');
        $response->setPublic();
        $response->setMaxAge(3600); // cache 1h

        return $response;
    }
}
