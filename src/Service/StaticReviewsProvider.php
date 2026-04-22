<?php

namespace App\Service;

use App\Repository\GoogleReviewRepository;

/**
 * Fournit les avis Google SANS passer par l'API Google.
 *
 * Priorité :
 *  1. Avis en base de données (table google_review, gérés via CRUD admin)
 *  2. Fallback fichier config/google_reviews.php si BDD vide
 *  3. Fallback vide
 *
 * Avantages :
 *  - Aucune API, aucune dépendance externe, aucun quota
 *  - L'admin gère les avis via /admin/avis-google (pas besoin de toucher au code)
 *  - Le fichier reste en backup si la BDD est indisponible
 */
class StaticReviewsProvider
{
    private ?array $fileData = null;

    public function __construct(
        private GoogleReviewRepository $googleReviewRepository,
        private string $projectDir,
    ) {}

    /**
     * Note globale (prioritaire : fichier ; fallback : moyenne calculée depuis BDD)
     */
    public function getOverallRating(): ?float
    {
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
     * Nombre total d'avis Google (prioritaire : fichier)
     */
    public function getTotalRatings(): ?int
    {
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
        // 1. BDD en priorité
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

        // 2. Fallback fichier
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
