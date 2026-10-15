<?php

namespace App\Controller;

use App\Repository\DailyStatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Fréquentation de la boutique — compteurs maison (StatsSubscriber), sans cookie ni IP.
 * Les ventes détaillées restent sur /admin/statistiques.
 */
class StatsAdminController extends AbstractController
{
    #[Route('/admin/stats', name: 'admin_stats_audience')]
    public function index(DailyStatRepository $stats): Response
    {
        $serie      = $stats->serieQuotidienne(30);
        $entonnoir  = $stats->entonnoir(7);

        return $this->render('admin/stats/audience.html.twig', [
            'serie'       => $serie,
            'entonnoir'   => $entonnoir,
            'topFiches'   => $stats->topFiches(7, 5),
            'visiteurs30' => array_sum($serie['visiteurs']),
            'paniers30'   => array_sum($serie['paniers']),
            'commandes30' => array_sum($serie['commandes']),
            'joursAvecDonnees' => count(array_filter($serie['visiteurs'])),
        ]);
    }
}
