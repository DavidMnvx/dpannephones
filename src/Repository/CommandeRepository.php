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
     * Total des ventes pour une année (et éventuellement un mois spécifique).
     *
     * @param int      $annee L'année ciblée
     * @param int|null $mois  1-12 pour filtrer sur un mois, null pour l'année entière
     */
    public function getTotalAnnee(int $annee, ?int $mois = null): float
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql  = 'SELECT COALESCE(SUM(total), 0) FROM commande WHERE YEAR(created_at) = :annee AND statut != :statut';
        $params = ['annee' => $annee, 'statut' => 'annulee'];

        if ($mois !== null) {
            $sql .= ' AND MONTH(created_at) = :mois';
            $params['mois'] = $mois;
        }

        return (float) $conn->executeQuery($sql, $params)->fetchOne();
    }

    /**
     * Top articles les plus vendus (tous statuts sauf 'annulee' et 'en_attente').
     *
     * Retourne un tableau de lignes :
     * [
     *   'article_id'     => 12,
     *   'article_name'   => 'iPhone 14 Pro',
     *   'article_image'  => 'xxx.svg',
     *   'article_categorie' => 'telephone',
     *   'article_price'  => 799.00,
     *   'quantite_vendue'=> 17,
     *   'ca_genere'      => 13583.00,
     *   'nb_commandes'   => 15
     * ]
     *
     * Note : on agrège sur CommandeItem pour gérer même les articles supprimés
     * (on garde le snapshot `article_name` / `prix` figés au moment de la commande).
     */
    public function getBestSellers(int $limit = 10, ?int $annee = null, ?int $mois = null): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Statuts comptabilisés comme ventes effectives
        $validStatuses = ['payee', 'en_preparation', 'expediee', 'livree', 'retiree'];

        $whereExtra = '';
        $params = ['statuses' => $validStatuses];
        $types  = ['statuses' => \Doctrine\DBAL\ArrayParameterType::STRING];

        if ($annee !== null) {
            $whereExtra .= ' AND YEAR(c.created_at) = :annee';
            $params['annee'] = $annee;
        }
        if ($mois !== null) {
            $whereExtra .= ' AND MONTH(c.created_at) = :mois';
            $params['mois'] = $mois;
        }

        $sql = '
            SELECT
                ci.article_id                    AS article_id,
                ci.article_name                  AS article_name,
                a.image                          AS article_image,
                a.categorie                      AS article_categorie,
                a.price                          AS article_price,
                SUM(ci.quantite)                 AS quantite_vendue,
                SUM(ci.prix * ci.quantite)       AS ca_genere,
                COUNT(DISTINCT c.id)             AS nb_commandes
            FROM commande_item ci
            INNER JOIN commande c ON c.id = ci.commande_id
            LEFT JOIN  article  a ON a.id = ci.article_id
            WHERE c.statut IN (:statuses)' . $whereExtra . '
            GROUP BY ci.article_id, ci.article_name, a.image, a.categorie, a.price
            ORDER BY quantite_vendue DESC, ca_genere DESC
            LIMIT ' . (int) $limit . '
        ';

        return $conn->executeQuery($sql, $params, $types)->fetchAllAssociative();
    }

    /**
     * Nombre total d'articles vendus (toutes commandes valides).
     */
    public function getTotalArticlesVendus(?int $annee = null, ?int $mois = null): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $where = ' WHERE c.statut IN ("payee", "en_preparation", "expediee", "livree", "retiree")';
        $params = [];

        if ($annee !== null) {
            $where .= ' AND YEAR(c.created_at) = :annee';
            $params['annee'] = $annee;
        }
        if ($mois !== null) {
            $where .= ' AND MONTH(c.created_at) = :mois';
            $params['mois'] = $mois;
        }

        $sql = 'SELECT COALESCE(SUM(ci.quantite), 0)
                FROM commande_item ci
                INNER JOIN commande c ON c.id = ci.commande_id' . $where;

        return (int) $conn->executeQuery($sql, $params)->fetchOne();
    }

    /**
     * Répartition des commandes par statut.
     * Retourne ['en_attente' => 2, 'payee' => 5, ...]
     */
    public function getCountByStatut(?int $annee = null, ?int $mois = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $where = '';
        $params = [];

        if ($annee !== null) {
            $where .= ' WHERE YEAR(created_at) = :annee';
            $params['annee'] = $annee;
        }
        if ($mois !== null) {
            $where .= ($where ? ' AND ' : ' WHERE ') . 'MONTH(created_at) = :mois';
            $params['mois'] = $mois;
        }

        $sql  = 'SELECT statut, COUNT(*) AS nb FROM commande' . $where . ' GROUP BY statut';
        $rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['statut']] = (int) $row['nb'];
        }

        return $result;
    }

    /**
     * Ventes par catégorie d'article (commandes valides uniquement).
     * Retourne : [['categorie' => 'telephone', 'quantite' => 5, 'ca' => 2500], ...]
     */
    public function getVentesParCategorie(?int $annee = null, ?int $mois = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $whereExtra = '';
        $params = [];

        if ($annee !== null) {
            $whereExtra .= ' AND YEAR(c.created_at) = :annee';
            $params['annee'] = $annee;
        }
        if ($mois !== null) {
            $whereExtra .= ' AND MONTH(c.created_at) = :mois';
            $params['mois'] = $mois;
        }

        $sql = '
            SELECT
                COALESCE(a.categorie, "autre")         AS categorie,
                SUM(ci.quantite)                       AS quantite,
                SUM(ci.prix * ci.quantite)             AS ca
            FROM commande_item ci
            INNER JOIN commande c ON c.id = ci.commande_id
            LEFT JOIN  article  a ON a.id = ci.article_id
            WHERE c.statut IN ("payee", "en_preparation", "expediee", "livree", "retiree")' . $whereExtra . '
            GROUP BY a.categorie
            ORDER BY ca DESC
        ';

        $rows = $conn->executeQuery($sql, $params)->fetchAllAssociative();

        return array_map(fn($r) => [
            'categorie' => $r['categorie'] ?: 'autre',
            'quantite'  => (int) $r['quantite'],
            'ca'        => (float) $r['ca'],
        ], $rows);
    }

    /**
     * Comparaison CA année courante vs année précédente (%)
     */
    public function getComparisonPreviousYear(int $currentYear): array
    {
        $prev = $this->getTotalAnnee($currentYear - 1);
        $curr = $this->getTotalAnnee($currentYear);

        $variation = null;
        if ($prev > 0) {
            $variation = round(($curr - $prev) / $prev * 100, 1);
        }

        return [
            'current' => $curr,
            'previous' => $prev,
            'variation_pct' => $variation, // null si pas de données précédentes
        ];
    }
}
