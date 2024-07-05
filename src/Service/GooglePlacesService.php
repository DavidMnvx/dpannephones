<?php


namespace App\Service;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class GooglePlacesService
{
    private $client;
    private $apiKey;
    private $logger;
    private $placeId;


    public function __construct(string $googleApiKey, string $googlePlaceId, LoggerInterface $logger)
    {
        $this->client = new Client();
        $this->apiKey = $googleApiKey;
        $this->placeId = $googlePlaceId;
        $this->logger = $logger;
    }

    public function getPlaceReviews(): array
    {
        // URL de base
            $base_url = 'https://maps.googleapis.com/maps/api/place/details/json?fields=reviews';

        // Construction de l'URL avec concaténation manuelle
            $final_url = $base_url . '&place_id=' . urlencode($this->placeId) . '&key=' . urlencode($this->apiKey);;
        try {
            $response = $this->client->request('GET', $final_url);

            $data = json_decode($response->getBody()->getContents(), true);
            $this->logger->info('Google Places API response',['data' => $data]);
            return $data['result']['reviews'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error('Error fetching reviews from Google Places API', ['exception' => $e]);
            return [];
        }
    }
}
