<?php

namespace App\Repository;

use App\Entity\DailyStat;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Lecture et incrément des compteurs quotidiens de fréquentation.
 *
 * @extends ServiceEntityRepository<DailyStat>
 */
class DailyStatRepository extends ServiceEntityRepository
{
    /** Statuts d'une commande réellement payée (même liste que les best-sellers). */
    private const STATUTS_PAYES = ['payee', 'en_preparation', 'expediee', 'livree', 'retiree'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyStat::class);
    }

    /** +1 sur le compteur du jour, en un seul aller-retour SQL. */
    public function increment(string $metric, string $label = '', ?\DateTimeInterface $jour = null): void
    {
        $jour ??= self::aujourdhui();

        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO daily_stat (date, metric, label, count) VALUES (:date, :metric, :label, 1)
             ON DUPLICATE KEY UPDATE count = count + 1',
            ['date' => $jour->format('Y-m-d'), 'metric' => $metric, 'label' => mb_substr($label, 0, 64)]
        );
    }

    /**
     * Série jour par jour sur les $nbJours derniers jours (aujourd'hui inclus),
     * jours vides remplis à 0.
     *
     * @return array{labels: string[], visiteurs: int[], paniers: int[], commandes: int[]}
     */
    public function serieQuotidienne(int $nbJours = 30): array
    {
        $fin   = self::aujourdhui();
        $debut = $fin->modify(sprintf('-%d days', $nbJours - 1));
        $conn  = $this->getEntityManager()->getConnection();

        $compteurs = $conn->executeQuery(
            'SELECT date, metric, SUM(count) AS nb FROM daily_stat
             WHERE date BETWEEN :debut AND :fin AND metric IN (:metrics)
             GROUP BY date, metric',
            ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d'),
             'metrics' => [DailyStat::VISITE_BOUTIQUE, DailyStat::AJOUT_PANIER]],
            ['metrics' => ArrayParameterType::STRING]
        )->fetchAllAssociative();

        $commandes = $conn->executeQuery(
            'SELECT DATE(created_at) AS date, COUNT(*) AS nb FROM commande
             WHERE created_at >= :debut AND statut IN (:statuts)
             GROUP BY DATE(created_at)',
            ['debut' => $debut->format('Y-m-d 00:00:00'), 'statuts' => self::STATUTS_PAYES],
            ['statuts' => ArrayParameterType::STRING]
        )->fetchAllKeyValue();

        $parJour = [];
        foreach ($compteurs as $c) {
            $parJour[$c['date']][$c['metric']] = (int) $c['nb'];
        }

        $serie = ['labels' => [], 'visiteurs' => [], 'paniers' => [], 'commandes' => []];
        for ($j = $debut; $j <= $fin; $j = $j->modify('+1 day')) {
            $cle = $j->format('Y-m-d');
            $serie['labels'][]    = $j->format('d/m');
            $serie['visiteurs'][] = $parJour[$cle][DailyStat::VISITE_BOUTIQUE] ?? 0;
            $serie['paniers'][]   = $parJour[$cle][DailyStat::AJOUT_PANIER] ?? 0;
            $serie['commandes'][] = (int) ($commandes[$cle] ?? 0);
        }

        return $serie;
    }

    /**
     * Entonnoir sur les $nbJours derniers jours : visiteurs → fiches → paniers → paiements → commandes.
     *
     * @return array{visiteurs: int, fiches: int, paniers: int, paiements: int, commandes: int, ca: float}
     */
    public function entonnoir(int $nbJours = 7): array
    {
        $debut = self::aujourdhui()->modify(sprintf('-%d days', $nbJours - 1));
        $conn  = $this->getEntityManager()->getConnection();

        $totaux = $conn->executeQuery(
            'SELECT metric, SUM(count) FROM daily_stat WHERE date >= :debut GROUP BY metric',
            ['debut' => $debut->format('Y-m-d')]
        )->fetchAllKeyValue();

        $cmd = $conn->executeQuery(
            'SELECT COUNT(*) AS nb, COALESCE(SUM(total), 0) AS ca FROM commande
             WHERE created_at >= :debut AND statut IN (:statuts)',
            ['debut' => $debut->format('Y-m-d 00:00:00'), 'statuts' => self::STATUTS_PAYES],
            ['statuts' => ArrayParameterType::STRING]
        )->fetchAssociative();

        return [
            'visiteurs' => (int) ($totaux[DailyStat::VISITE_BOUTIQUE] ?? 0),
            'fiches'    => (int) ($totaux[DailyStat::VUE_FICHE] ?? 0),
            'paniers'   => (int) ($totaux[DailyStat::AJOUT_PANIER] ?? 0),
            'paiements' => (int) ($totaux[DailyStat::CHECKOUT] ?? 0),
            'commandes' => (int) $cmd['nb'],
            'ca'        => (float) $cmd['ca'],
        ];
    }

    /**
     * Articles les plus vus sur les $nbJours derniers jours (nom joint via le label).
     *
     * @return array<array{id: int, nom: ?string, vues: int}>
     */
    public function topFiches(int $nbJours = 7, int $limite = 5): array
    {
        $debut = self::aujourdhui()->modify(sprintf('-%d days', $nbJours - 1));

        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            'SELECT s.label AS id, a.name AS nom, SUM(s.count) AS vues
             FROM daily_stat s
             LEFT JOIN article a ON a.id = CAST(s.label AS UNSIGNED)
             WHERE s.metric = :metric AND s.date >= :debut
             GROUP BY s.label, a.name
             ORDER BY vues DESC
             LIMIT ' . max(1, $limite),
            ['metric' => DailyStat::VUE_FICHE, 'debut' => $debut->format('Y-m-d')]
        )->fetchAllAssociative();

        return array_map(fn ($r) => ['id' => (int) $r['id'], 'nom' => $r['nom'], 'vues' => (int) $r['vues']], $rows);
    }

    /** Visiteurs boutique du jour (tuile du dashboard). */
    public function visiteursDuJour(): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COALESCE(SUM(count), 0) FROM daily_stat WHERE date = :date AND metric = :metric',
            ['date' => self::aujourdhui()->format('Y-m-d'), 'metric' => DailyStat::VISITE_BOUTIQUE]
        );
    }

    /** La journée se découpe à l'heure de Paris, quel que soit le fuseau du serveur. */
    public static function aujourdhui(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
    }
}
