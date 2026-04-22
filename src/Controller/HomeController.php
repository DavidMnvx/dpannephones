<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\ReviewRepository;
use App\Service\StaticReviewsProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private StaticReviewsProvider $reviewsProvider,
        private ArticleRepository     $articleRepository,
        private ReviewRepository      $reviewRepository,
        private LoggerInterface       $logger,
    ) {}

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        // Avis Google (statiques en BDD via admin ou fichier fallback)
        $reviews            = $this->reviewsProvider->getReviews();
        $rating             = $this->reviewsProvider->getOverallRating();
        $totalRatings       = $this->reviewsProvider->getTotalRatings();
        $ratingDistribution = $this->reviewsProvider->getRatingDistribution();

        // Avis clients du site (derniers 6 approuvés)
        $clientReviews = $this->reviewRepository->findApproved(6);

        // Derniers articles pour la bande défilante
        $dernierArticles = $this->articleRepository->findBy([], ['id' => 'DESC'], 10);

        return $this->render('home/index.html.twig', [
            'reviews'             => $reviews,
            'clientReviews'       => $clientReviews,
            'rating'              => $rating,
            'totalRatings'        => $totalRatings,
            'ratingDistribution'  => $ratingDistribution,
            'dernierArticles'     => $dernierArticles,
            'controller_name'     => 'HomeController',
        ]);
    }
}
