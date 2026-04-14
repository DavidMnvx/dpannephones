<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use App\Repository\ArticleRepository;
use App\Service\GooglePlacesService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    private $googlePlacesService;
    private $logger;
    private $httpClient;
    private $placeId;
    private $apiKey;
    private $articleRepository;

    public function __construct(string $googleApiKey, string $googlePlaceId, GooglePlacesService $googlePlacesService, LoggerInterface $logger, HttpClientInterface $httpClient, ArticleRepository $articleRepository)
    {
        $this->googlePlacesService = $googlePlacesService;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->apiKey = $googleApiKey;
        $this->placeId = $googlePlaceId;
        $this->articleRepository = $articleRepository;
    }

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        try {
            // Récupérer les avis via le service GooglePlacesService
            $reviews = $this->googlePlacesService->getPlaceReviews();

            // Appeler l'API Google Places pour obtenir la note globale et le nombre total d'avis
            $response = $this->httpClient->request('GET', "https://maps.googleapis.com/maps/api/place/details/json?placeid={$this->placeId}&key={$this->apiKey}", ['timeout' => 5]);
            $data = $response->toArray();

            // Journaliser les avis reçus
            $this->logger->info('Reviews received', ['reviews' => $reviews]);

            // Récupérer la note globale et le nombre total d'avis
            $rating = $data['result']['rating'] ?? null;
            $totalRatings = $data['result']['user_ratings_total'] ?? null;

            if ($rating === null || $totalRatings === null) {
                throw new \Exception('Failed to retrieve rating or total ratings from Google API response.');
            }

            // Calculer la distribution des avis
            $ratingDistribution = $this->calculateRatingDistribution($reviews);

            // Log ratingDistribution and totalRatings for debugging
            $this->logger->info('Rating distribution', ['ratingDistribution' => $ratingDistribution]);
            $this->logger->info('Total ratings', ['totalRatings' => $totalRatings]);

            // Rendre le template avec les données récupérées
            $dernierArticles = $this->articleRepository->findBy([], ['id' => 'DESC'], 10);

            return $this->render('home/index.html.twig', [
                'reviews' => $reviews,
                'controller_name' => 'PlaceController',
                'rating' => $rating,
                'totalRatings' => $totalRatings,
                'ratingDistribution' => $ratingDistribution,
                'dernierArticles' => $dernierArticles,
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Google API unavailable, loading page without reviews.', [
                'exception' => $e->getMessage()
            ]);

            $dernierArticles = $this->articleRepository->findBy([], ['id' => 'DESC'], 10);

            return $this->render('home/index.html.twig', [
                'reviews' => [],
                'controller_name' => 'PlaceController',
                'rating' => null,
                'totalRatings' => null,
                'ratingDistribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
                'dernierArticles' => $dernierArticles,
            ]);
        }

    }

    private function calculateRatingDistribution(array $reviews): array
    {
        $distribution = [
            5 => 0,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0,
        ];

        foreach ($reviews as $review) {
            $rating = $review['rating'];
            if (isset($distribution[$rating])) {
                $distribution[$rating]++;
            }
        }
        
        return $distribution;
    }
}
