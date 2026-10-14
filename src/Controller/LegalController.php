<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LegalController extends AbstractController
{
    #[Route('/mentions-legales', name: 'legal_mentions', methods: ['GET'])]
    public function mentions(): Response
    {
        return $this->render('legal/mentions_legales.html.twig');
    }

    #[Route('/cgv', name: 'legal_cgv', methods: ['GET'])]
    public function cgv(): Response
    {
        return $this->render('legal/cgv.html.twig');
    }

    #[Route('/livraison-retours', name: 'legal_livraison', methods: ['GET'])]
    public function livraison(): Response
    {
        return $this->render('legal/livraison_retours.html.twig', [
            'modes' => \App\Service\ShippingOptionsResolver::OPTIONS,
        ]);
    }

    #[Route('/confidentialite', name: 'legal_confidentialite', methods: ['GET'])]
    public function confidentialite(): Response
    {
        return $this->render('legal/confidentialite.html.twig');
    }
}
