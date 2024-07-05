<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use App\Service\GooglePlacesService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{   
    private $googlePlacesService;

    private $logger;

    public function __construct(GooglePlacesService $googlePlacesService, LoggerInterface $logger)
    {
        $this->googlePlacesService = $googlePlacesService;
        $this->logger = $logger;
    }
    
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        $reviews = $this->googlePlacesService->getPlaceReviews();

        $this->logger->info('Reviews received', ['reviews' => $reviews]);

        return $this->render('home/index.html.twig', [
            'reviews' => $reviews,
            'controller_name' => 'PlaceController',
        ]);
    }
}
