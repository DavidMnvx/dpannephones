<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Gestion des catégories de la boutique depuis l'admin.
 * Ajouter une catégorie ici la fait apparaître partout : formulaire article,
 * sidebar boutique, onglets admin, options de livraison.
 */
#[Route('/admin/categories')]
class CategoryAdminController extends AbstractController
{
    /** Icônes proposées à la création (classe FontAwesome => libellé) */
    public const ICON_CHOICES = [
        'fas fa-gamepad'      => 'Manette (gaming)',
        'fas fa-desktop'      => 'Écran (PC fixe)',
        'fas fa-laptop'       => 'PC portable',
        'fas fa-mobile-alt'   => 'Téléphone',
        'fas fa-tablet-alt'   => 'Tablette',
        'fas fa-headphones'   => 'Casque (accessoires)',
        'fas fa-shield-alt'   => 'Bouclier (coques)',
        'fas fa-tint'         => 'Goutte (hydrogel)',
        'fas fa-plug'         => 'Prise (chargeurs)',
        'fas fa-camera'       => 'Appareil photo',
        'fas fa-clock'        => 'Montre',
        'fas fa-keyboard'     => 'Clavier',
        'fas fa-mouse'        => 'Souris',
        'fas fa-tv'           => 'TV / Écran',
        'fas fa-hdd'          => 'Disque dur (stockage)',
        'fas fa-sd-card'      => 'Carte SD / clé',
        'fas fa-memory'       => 'Barrette RAM',
        'fas fa-microchip'    => 'Composant / puce',
        'fas fa-gem'          => 'Verre trempé',
        'fas fa-battery-full' => 'Batterie',
        'fab fa-usb'          => 'Câble USB',
        'fas fa-volume-up'    => 'Enceinte / son',
        'fas fa-box'          => 'Boîte (générique)',
    ];

    #[Route('', name: 'admin_categories_index', methods: ['GET'])]
    public function index(CategoryRepository $repo, EntityManagerInterface $em): Response
    {
        // Nombre d'articles par catégorie (pour bloquer les suppressions risquées)
        $counts = [];
        foreach ($em->getRepository(Article::class)->createQueryBuilder('a')
                     ->select('a.categorie AS cat, COUNT(a.id) AS nb')
                     ->groupBy('a.categorie')->getQuery()->getArrayResult() as $row) {
            $counts[$row['cat'] ?? ''] = (int) $row['nb'];
        }

        return $this->render('admin/category/index.html.twig', [
            'categories'     => $repo->findAllOrdered(),
            'articleCounts'  => $counts,
            'iconChoices'    => self::ICON_CHOICES,
            'specsTemplates' => Category::SPECS_TEMPLATES,
            'families'       => $repo->getFamilies(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_categories_new', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $repo,
        SluggerInterface $slugger
    ): Response {
        $label = trim((string) $request->request->get('label', ''));
        if ($label === '') {
            $this->addFlash('warning', 'Le nom de la catégorie est requis.');
            return $this->redirectToRoute('admin_categories_index');
        }

        $slug = strtolower($slugger->slug($label)->toString());
        $slug = str_replace('-', '_', $slug);
        if ($repo->findOneBy(['slug' => $slug])) {
            $this->addFlash('warning', 'Une catégorie avec ce nom existe déjà.');
            return $this->redirectToRoute('admin_categories_index');
        }

        $maxPosition = 0;
        foreach ($repo->findAllOrdered() as $c) {
            $maxPosition = max($maxPosition, $c->getPosition());
        }

        $category = (new Category())
            ->setSlug($slug)
            ->setLabel($label)
            ->setIcon($this->sanitizeIcon((string) $request->request->get('icon', '')))
            ->setShippingTier((int) $request->request->get('shipping_tier', 1))
            ->setSpecsTemplate($this->sanitizeTemplate((string) $request->request->get('specs_template', '')))
            ->setFamily((string) $request->request->get('family', ''))
            ->setPosition($maxPosition + 10);

        $em->persist($category);
        $em->flush();
        $this->addFlash('success', 'Catégorie "' . $label . '" ajoutée — elle est disponible immédiatement dans la boutique et le formulaire article.');

        return $this->redirectToRoute('admin_categories_index');
    }

    #[Route('/{id}/edit', name: 'admin_categories_edit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function edit(Category $category, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('edit-category-' . $category->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $label = trim((string) $request->request->get('label', ''));
        if ($label !== '') {
            $category->setLabel($label);
        }
        $category->setIcon($this->sanitizeIcon((string) $request->request->get('icon', $category->getIcon())));
        $category->setShippingTier((int) $request->request->get('shipping_tier', $category->getShippingTier()));
        $category->setSpecsTemplate($this->sanitizeTemplate((string) $request->request->get('specs_template', '')));
        $category->setPosition((int) $request->request->get('position', $category->getPosition()));
        $category->setFamily((string) $request->request->get('family', ''));

        $em->flush();
        $this->addFlash('success', 'Catégorie "' . $category->getLabel() . '" mise à jour.');

        return $this->redirectToRoute('admin_categories_index');
    }

    #[Route('/{id}/delete', name: 'admin_categories_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Category $category, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-category-' . $category->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $nbArticles = (int) $em->getRepository(Article::class)->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.categorie = :slug')->setParameter('slug', $category->getSlug())
            ->getQuery()->getSingleScalarResult();

        if ($nbArticles > 0) {
            $this->addFlash('warning', sprintf(
                'Impossible de supprimer "%s" : %d article(s) utilisent encore cette catégorie. Changez d\'abord leur catégorie.',
                $category->getLabel(),
                $nbArticles
            ));
            return $this->redirectToRoute('admin_categories_index');
        }

        $em->remove($category);
        $em->flush();
        $this->addFlash('success', 'Catégorie supprimée.');

        return $this->redirectToRoute('admin_categories_index');
    }

    private function sanitizeIcon(string $icon): string
    {
        return isset(self::ICON_CHOICES[$icon]) ? $icon : 'fas fa-box';
    }

    private function sanitizeTemplate(string $template): ?string
    {
        return isset(Category::SPECS_TEMPLATES[$template]) ? $template : null;
    }
}
