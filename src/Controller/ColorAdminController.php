<?php

namespace App\Controller;

use App\Entity\ProductColor;
use App\Repository\ProductColorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/couleurs')]
class ColorAdminController extends AbstractController
{
    #[Route('', name: 'admin_colors_index', methods: ['GET'])]
    public function index(ProductColorRepository $repo): Response
    {
        return $this->render('admin/color/index.html.twig', [
            'colors' => $repo->findAllOrdered(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_colors_new', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, ProductColorRepository $repo): Response
    {
        $name = trim((string) $request->request->get('name', ''));
        $hex  = trim((string) $request->request->get('hex', ''));

        if ($name === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            $this->addFlash('warning', 'Nom et code hex (#RRGGBB) requis.');
            return $this->redirectToRoute('admin_colors_index');
        }
        if ($repo->findOneBy(['name' => $name])) {
            $this->addFlash('warning', 'Cette couleur existe déjà.');
            return $this->redirectToRoute('admin_colors_index');
        }

        $color = (new ProductColor())->setName($name)->setHex(strtoupper($hex));
        $em->persist($color);
        $em->flush();
        $this->addFlash('success', 'Couleur "' . $name . '" ajoutée.');

        return $this->redirectToRoute('admin_colors_index');
    }

    #[Route('/{id}/delete', name: 'admin_colors_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(ProductColor $color, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-color-' . $color->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $em->remove($color);
        $em->flush();
        $this->addFlash('success', 'Couleur supprimée.');

        return $this->redirectToRoute('admin_colors_index');
    }

    /**
     * Endpoint AJAX : crée une couleur et renvoie l'objet en JSON.
     * Utilisé par le bouton "Créer une couleur" du formulaire d'article.
     */
    #[Route('/ajax/create', name: 'admin_colors_ajax_create', methods: ['POST'])]
    public function ajaxCreate(Request $request, EntityManagerInterface $em, ProductColorRepository $repo): JsonResponse
    {
        $name = trim((string) $request->request->get('name', ''));
        $hex  = trim((string) $request->request->get('hex', ''));

        if ($name === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            return new JsonResponse(['ok' => false, 'error' => 'Nom et code hex requis (#RRGGBB).'], 400);
        }
        if ($existing = $repo->findOneBy(['name' => $name])) {
            return new JsonResponse([
                'ok'    => true,
                'color' => ['id' => $existing->getId(), 'name' => $existing->getName(), 'hex' => $existing->getHex()],
                'note'  => 'Existe déjà, réutilisée.',
            ]);
        }
        $color = (new ProductColor())->setName($name)->setHex(strtoupper($hex));
        $em->persist($color);
        $em->flush();

        return new JsonResponse([
            'ok'    => true,
            'color' => ['id' => $color->getId(), 'name' => $color->getName(), 'hex' => $color->getHex()],
        ]);
    }
}
