<?php

namespace App\Controller;

use App\Entity\Partenaire;
use App\Repository\PartenaireRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PartenaireController extends AbstractController
{
    #[Route('/partenaires', name: 'partenaires_index')]
    public function index(PartenaireRepository $repo): Response
    {
        $premium = $repo->findByType(Partenaire::TYPE_PREMIUM);
        $normal  = $repo->findByType(Partenaire::TYPE_NORMAL);

        return $this->render('partenaires/index.html.twig', [
            'premium' => $premium,
            'normal'  => $normal,
        ]);
    }
}
