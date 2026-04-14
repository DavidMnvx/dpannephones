<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * Retourne les ventes par mois pour une année donnée.
     * Résultat : [['mois' => 1, 'total' => 450.00, 'nb' => 3], ...]
     */
    public function getVentesParMois(int $annee): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                MONTH(created_at) AS mois,
                COUNT(id)         AS nb,
                SUM(total)        AS total
            FROM commande
            WHERE YEAR(created_at) = :annee
              AND statut != :statut
            GROUP BY MONTH(created_at)
            ORDER BY mois
        ';

        $rows = $conn->executeQuery($sql, ['annee' => $annee, 'statut' => 'annulee'])->fetchAllAssociative();

        // Remplir tous les mois (1-12) même si aucune vente
        $result = [];
        $byMonth = array_column($rows, null, 'mois');
        for ($m = 1; $m <= 12; $m++) {
            $result[] = [
                'mois'  => $m,
                'total' => isset($byMonth[$m]) ? (float) $byMonth[$m]['total'] : 0,
                'nb'    => isset($byMonth[$m]) ? (int)   $byMonth[$m]['nb']    : 0,
            ];
        }

        return $result;
    }

    /**
     * Total des ventes pour une année.
     */
    public function getTotalAnnee(int $annee): float
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql  = 'SELECT COALESCE(SUM(total), 0) FROM commande WHERE YEAR(created_at) = :annee AND statut != :statut';
        return (float) $conn->executeQuery($sql, ['annee' => $annee, 'statut' => 'annulee'])->fetchOne();
    }
}
