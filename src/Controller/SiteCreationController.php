<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Page "Création de sites" — vitrine NosWeb, le créateur de ce site.
 * Renvoie le visiteur vers nosweb.fr pour toute demande.
 */
class SiteCreationController extends AbstractController
{
    #[Route('/creation-de-sites', name: 'site_creation', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('site_creation/index.html.twig');
    }
}
