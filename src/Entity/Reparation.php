<?php

// src/Entity/Reparation.php
namespace App\Entity;

use App\Repository\ReparationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReparationRepository::class)]
class Reparation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'decimal', scale: 2)]
    private ?float $prix = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length:10)]
    private ?bool $hasPhone = false;

    #[ORM\Column(length:10)]
    private ?bool $hasTablet = false;

    /**
     * Ordre d'affichage dans la liste publique (plus petit = en premier).
     * Permet à l'admin de réorganiser facilement les réparations.
     */
    #[ORM\Column]
    private int $sortOrder = 0;

    /**
     * Réparation "universelle" / "service commun" : s'applique à tous les modèles.
     * Exemple : Tiroir SIM, Désoxydation, Transfert de données, Diagnostique.
     * Quand true, le champ `model` est généralement null.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $isUniversal = false;

    /**
     * Classe d'icône FontAwesome (ex: 'fa-sim-card', 'fa-tint', 'fa-stethoscope').
     * Utilisé surtout pour les réparations universelles affichées en "services communs".
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\ManyToOne(targetEntity: Model::class, inversedBy: 'reparations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Model $model = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): self
    {
        $this->prix = $prix;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getHasPhone(): ?bool
    {
        return $this->hasPhone;
    }

    public function setHasPhone(bool $hasPhone): self
    {
        $this->hasPhone = $hasPhone;

        return $this;
    }

    public function getHasTablet(): ?bool
    {
        return $this->hasTablet;
    }

    public function setHasTablet(bool $hasTablet): self
    {
        $this->hasTablet = $hasTablet;

        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function setModel(?Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function isUniversal(): bool
    {
        return $this->isUniversal;
    }

    public function getIsUniversal(): bool
    {
        return $this->isUniversal;
    }

    public function setIsUniversal(bool $isUniversal): self
    {
        $this->isUniversal = $isUniversal;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $icon = $icon !== null ? trim($icon) : null;
        $this->icon = ($icon === '' || $icon === null) ? null : $icon;
        return $this;
    }
}
