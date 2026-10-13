<?php

namespace App\Service;

use App\Repository\GoogleReviewRepository;

/**
 * Fournit les avis Google du site (accueil, page Avis, note du pied de page).
 *
 * Priorité :
 *  1. EN DIRECT de la fiche Google (GooglePlacesService) quand la clé de l'API est posée :
 *     note, nombre d'avis et avis commentés non masqués dans l'admin
 *  2. Avis en base de données (table google_review, gérés via CRUD admin)
 *  3. Fallback fichier config/google_reviews.php si BDD vide
 *  4. Fallback vide
 *
 * Les niveaux 2 et 3 sont le secours : sans clé, si Google ne répond pas,
 * ou si l'admin a masqué tous les avis lus en direct.
 */
class StaticReviewsProvider
{
    private ?array $fileData = null;

    public function __construct(
        private GoogleReviewRepository $googleReviewRepository,
        private string $projectDir,
        private GooglePlacesService $googlePlaces,
    ) {}

    /**
     * Note globale (prioritaire : Google en direct, puis fichier ; fallback : moyenne calculée depuis BDD)
     */
    public function getOverallRating(): ?float
    {
        $place = $this->googlePlaces->getPlace();
        if ($place !== null) {
            return $place['total'] > 0 ? $place['rating'] : null;
        }

        $fileData = $this->loadFile();
        if ($fileData && isset($fileData['overall_rating'])) {
            return (float) $fileData['overall_rating'];
        }

        // Calcul depuis BDD si pas de fichier
        $reviews = $this->googleReviewRepository->findActiveOrdered();
        if (empty($reviews)) {
            return null;
        }

        $sum = 0;
        foreach ($reviews as $r) {
            $sum += $r->getRating();
        }

        return round($sum / count($reviews), 1);
    }

    /**
     * Nombre total d'avis Google (prioritaire : Google en direct, puis fichier)
     */
    public function getTotalRatings(): ?int
    {
        $place = $this->googlePlaces->getPlace();
        if ($place !== null) {
            return $place['total'] ?: null;
        }

        $fileData = $this->loadFile();
        if ($fileData && isset($fileData['total_ratings'])) {
            return (int) $fileData['total_ratings'];
        }

        return count($this->googleReviewRepository->findActiveOrdered()) ?: null;
    }

    /**
     * Liste des avis affichables.
     *
     * @return array<int, array>
     */
    public function getReviews(): array
    {
        // 1. En direct de Google : les avis commentés que l'admin n'a pas masqués
        $live = array_values(array_filter(
            $this->googlePlaces->getPlace()['reviews'] ?? [],
            fn(array $r) => !$r['hidden'] && $r['text'] !== '',
        ));
        if (!empty($live)) {
            return $live;
        }

        // 2. BDD
        $dbReviews = $this->googleReviewRepository->findActiveOrdered();
        if (!empty($dbReviews)) {
            return array_map(fn($r) => [
                'author_name'       => $r->getAuthorName(),
                'rating'            => $r->getRating(),
                'relative_time'     => $r->getRelativeTime(),
                'text'              => $r->getText(),
                'profile_photo_url' => $r->getProfilePhotoUrl(),
            ], $dbReviews);
        }

        // 3. Fallback fichier
        $fileData = $this->loadFile();
        return $fileData['reviews'] ?? [];
    }

    /**
     * Distribution des notes (1 à 5) sur les avis affichés
     */
    public function getRatingDistribution(): array
    {
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($this->getReviews() as $review) {
            $rating = (int) ($review['rating'] ?? 0);
            if (isset($distribution[$rating])) {
                $distribution[$rating]++;
            }
        }

        return $distribution;
    }

    /**
     * Charge une seule fois le fichier fallback en mémoire.
     */
    private function loadFile(): ?array
    {
        if ($this->fileData !== null) {
            return $this->fileData ?: null;
        }

        $path = $this->projectDir . '/config/google_reviews.php';

        if (file_exists($path)) {
            $this->fileData = require $path;
            return $this->fileData;
        }

        $this->fileData = [];
        return null;
    }
}
