<?php
namespace App\Controller;

use App\Entity\Marque;
use App\Entity\Model;
use App\Entity\Reparation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReparationsController extends AbstractController
{
    #[Route('/reparations', name: 'reparations_home')]
    #[Route('/reparations/type', name: 'choix_type')]
    public function choisirType(): Response
    {
        // Retourne la vue avec les choix (téléphone, tablette)
        return $this->render('reparations/choisir_type.html.twig');
    }

    #[Route('/marques/{type}', name: 'choix_marque')]
    public function choisirMarque(string $type, EntityManagerInterface $entityManager): Response
    {
        $marqueRepository = $entityManager->getRepository(Marque::class);

        // Vérifie la valeur de $type et effectue une requête en conséquence
        if ($type === 'phone') {
            $marques = $marqueRepository->findBy(['hasPhone' => true], ['name' => 'ASC']);
        } elseif ($type === 'tablet') {
            $marques = $marqueRepository->findBy(['hasTablet' => true], ['name' => 'ASC']);
        } else {
            $marques = $marqueRepository->findBy([], ['name' => 'ASC']);
        }

        return $this->render('reparations/choisir_marque.html.twig', [
            'marques' => $marques,
            'type' => $type,
        ]);
    }

    #[Route('/modeles/{marqueId}/{type}', name: 'choix_modele')]
    public function choisirModele(int $marqueId, string $type, EntityManagerInterface $entityManager): Response
    {
        $marque = $entityManager->getRepository(Marque::class)->find($marqueId);
        if (!$marque) {
            throw $this->createNotFoundException('Aucune marque trouvée pour l\'ID fourni : ' . $marqueId);
        }

        $modeleRepository = $entityManager->getRepository(Model::class);

        if ($type === 'phone') {
            $modeles = $modeleRepository->findBy(['marque' => $marque, 'hasPhone' => true]);
        } elseif ($type === 'tablet') {
            $modeles = $modeleRepository->findBy(['marque' => $marque, 'hasTablet' => true]);
        } else {
            $modeles = $modeleRepository->findBy(['marque' => $marque]);
        }

        return $this->render('reparations/choisir_modele.html.twig', [
            'modeles' => $modeles,
            'marque' => $marque,
            'type' => $type,
        ]);
    }

    #[Route('/reparations/{modeleName}/{type}', name: 'liste_reparations')]
    public function listeReparations(string $modeleName, string $type, EntityManagerInterface $entityManager): Response
    {
        $modeleRepository = $entityManager->getRepository(Model::class);
        $modele = $modeleRepository->findOneBy(['name' => $modeleName]);

        if (!$modele) {
            throw $this->createNotFoundException('Le modèle n\'a pas été trouvé.');
        }

        $reparationRepository = $entityManager->getRepository(Reparation::class);

        if ($type === 'phone') {
            $reparations = $reparationRepository->findBy(['model' => $modele, 'hasPhone' => true]);
        } elseif ($type === 'tablet') {
            $reparations = $reparationRepository->findBy(['model' => $modele, 'hasTablet' => true]);
        } else {
            $reparations = $reparationRepository->findBy(['model' => $modele]);
        }

        return $this->render('reparations/liste_reparations.html.twig', [
            'reparations' => $reparations,
            'modele' => $modele,
            'type' => $type,
        ]);
    }
}
