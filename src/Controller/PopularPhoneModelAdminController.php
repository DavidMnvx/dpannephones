<?php

namespace App\Controller;

use App\Entity\PopularPhoneModel;
use App\Repository\PopularPhoneModelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/telephones-populaires')]
class PopularPhoneModelAdminController extends AbstractController
{
    #[Route('', name: 'admin_popular_phones_index', methods: ['GET'])]
    public function index(PopularPhoneModelRepository $repo): Response
    {
        return $this->render('admin/popular_phone/index.html.twig', [
            'grouped' => $repo->findGroupedByBrandAndFamille(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_popular_phones_new', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, PopularPhoneModelRepository $repo): Response
    {
        $brand   = trim((string) $request->request->get('brand', ''));
        $model   = trim((string) $request->request->get('model_name', ''));
        $famille = trim((string) $request->request->get('famille', ''));
        $famille = $famille === '' ? null : $famille;

        if ($brand === '' || $model === '') {
            $this->addFlash('warning', 'Marque et nom du modèle requis.');
            return $this->redirectToRoute('admin_popular_phones_index');
        }
        if ($repo->findOneBy(['brand' => $brand, 'modelName' => $model])) {
            $this->addFlash('warning', 'Ce modèle existe déjà.');
            return $this->redirectToRoute('admin_popular_phones_index');
        }

        $ppm = (new PopularPhoneModel())
            ->setBrand($brand)
            ->setModelName($model)
            ->setFamille($famille)
            ->setSortOrder(9999);
        $em->persist($ppm);
        $em->flush();
        $this->addFlash('success', $brand . ' — ' . $model . ' ajouté.');

        return $this->redirectToRoute('admin_popular_phones_index');
    }

    #[Route('/{id}/delete', name: 'admin_popular_phones_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(PopularPhoneModel $ppm, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-ppm-' . $ppm->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $em->remove($ppm);
        $em->flush();
        $this->addFlash('success', 'Modèle supprimé.');

        return $this->redirectToRoute('admin_popular_phones_index');
    }
}
