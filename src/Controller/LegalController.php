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

    #[Route('/confidentialite', name: 'legal_confidentialite', methods: ['GET'])]
    public function confidentialite(): Response
    {
        return $this->render('legal/confidentialite.html.twig');
    }
}
