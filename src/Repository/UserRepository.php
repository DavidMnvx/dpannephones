<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Nouveaux clients par mois pour une année donnée.
     * Retourne 12 lignes (rempli avec 0 si aucune inscription ce mois).
     * Format : [['mois' => 1, 'nb' => 3], ['mois' => 2, 'nb' => 0], ...]
     */
    public function getNouveauxClientsParMois(int $annee): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT MONTH(created_at) AS mois, COUNT(*) AS nb
            FROM user
            WHERE YEAR(created_at) = :annee
            GROUP BY MONTH(created_at)
            ORDER BY mois
        ';

        $rows = $conn->executeQuery($sql, ['annee' => $annee])->fetchAllAssociative();
        $byMonth = array_column($rows, null, 'mois');

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $result[] = [
                'mois' => $m,
                'nb'   => isset($byMonth[$m]) ? (int) $byMonth[$m]['nb'] : 0,
            ];
        }

        return $result;
    }

    /**
     * Nombre d'inscriptions cette semaine / ce mois / total
     */
    public function getInscriptionsStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $rows = $conn->executeQuery('
            SELECT
                SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))  AS week,
                SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS month,
                SUM(created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)) AS year,
                COUNT(*)                                             AS total,
                SUM(is_verified)                                     AS verifies
            FROM user
        ')->fetchAssociative();

        return [
            'week'     => (int) ($rows['week'] ?? 0),
            'month'    => (int) ($rows['month'] ?? 0),
            'year'     => (int) ($rows['year'] ?? 0),
            'total'    => (int) ($rows['total'] ?? 0),
            'verifies' => (int) ($rows['verifies'] ?? 0),
        ];
    }
}
