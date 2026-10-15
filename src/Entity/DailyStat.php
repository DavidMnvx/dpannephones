<?php

namespace App\Entity;

use App\Repository\DailyStatRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Compteur quotidien de fréquentation (statistiques maison, sans cookie ni IP).
 *
 * Une ligne = un jour × une métrique × un libellé (ex. id d'article pour vue_fiche,
 * chaîne vide sinon). Les incréments passent en SQL direct (upsert) dans
 * StatsSubscriber : l'entité ne sert qu'à garder le schéma Doctrine aligné.
 */
#[ORM\Entity(repositoryClass: DailyStatRepository::class)]
#[ORM\Table(name: 'daily_stat')]
#[ORM\UniqueConstraint(name: 'uniq_daily_stat', columns: ['date', 'metric', 'label'])]
#[ORM\Index(name: 'idx_daily_stat_metric_date', columns: ['metric', 'date'])]
class DailyStat
{
    public const VISITE_BOUTIQUE = 'visite_boutique';
    public const VUE_FICHE       = 'vue_fiche';
    public const AJOUT_PANIER    = 'ajout_panier';
    public const CHECKOUT        = 'checkout';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $date;

    #[ORM\Column(length: 30)]
    private string $metric;

    #[ORM\Column(length: 64, options: ['default' => ''])]
    private string $label = '';

    #[ORM\Column(options: ['default' => 0])]
    private int $count = 0;

    public function getId(): ?int { return $this->id; }
    public function getDate(): \DateTimeInterface { return $this->date; }
    public function getMetric(): string { return $this->metric; }
    public function getLabel(): string { return $this->label; }
    public function getCount(): int { return $this->count; }
}
