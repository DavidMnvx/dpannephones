<?php

namespace App\Entity;

use App\Repository\PopularPhoneModelRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catalogue de modèles de téléphone les plus populaires (séparé de Model utilisé pour les réparations).
 * Alimente le picker "Marque / Série / Modèle" des articles Coque et Film Hydrogel.
 */
#[ORM\Entity(repositoryClass: PopularPhoneModelRepository::class)]
#[ORM\Table(name: 'popular_phone_model')]
#[ORM\UniqueConstraint(name: 'UNIQ_PPM_BRAND_MODEL', columns: ['brand', 'model_name'])]
class PopularPhoneModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    private ?string $brand = null;

    #[ORM\Column(length: 120)]
    private ?string $modelName = null;

    /**
     * Sous-catégorie (Série S, Série A, Note, Flip, Fold, Pro, mini…).
     * Null = "Autres" ou pas de sous-groupement.
     */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $famille = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getBrand(): ?string { return $this->brand; }
    public function setBrand(string $brand): self { $this->brand = $brand; return $this; }
    public function getModelName(): ?string { return $this->modelName; }
    public function setModelName(string $modelName): self { $this->modelName = $modelName; return $this; }
    public function getFamille(): ?string { return $this->famille; }
    public function setFamille(?string $famille): self { $this->famille = $famille; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): self { $this->sortOrder = $sortOrder; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
}
