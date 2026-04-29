<?php

// src/Entity/Model.php
namespace App\Entity;

use App\Repository\ModelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModelRepository::class)]
class Model
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length:255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length:10)]
    private ?bool $hasPhone = false;

    #[ORM\Column(length:10)]
    private ?bool $hasTablet = false;

    /**
     * Sous-catégorie / gamme du modèle (ex: "Série A", "Série S", "Redmi", "Poco"…).
     * Sert à grouper les modèles d'une même marque sur la page publique.
     * Null = pas de sous-catégorie (affichage plat).
     */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $famille = null;

    #[ORM\ManyToOne(targetEntity: Marque::class, inversedBy: 'models')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Marque $marque = null;

    #[ORM\OneToMany(targetEntity: Reparation::class, mappedBy: 'model')]
    private Collection $reparations;

    public function __construct()
    {
        $this->reparations = new ArrayCollection();
    }

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

    public function getImage(): ?string
    {
        return $this->image;
    }
    public function setImage(string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getMarque(): ?Marque
    {
        return $this->marque;
    }

    public function setMarque(?Marque $marque): self
    {
        $this->marque = $marque;

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

    public function getFamille(): ?string
    {
        return $this->famille;
    }

    public function setFamille(?string $famille): self
    {
        // Normalise : trim + null si vide
        $famille = $famille !== null ? trim($famille) : null;
        $this->famille = ($famille === '' || $famille === null) ? null : $famille;

        return $this;
    }

    /**
     * @return Collection|Reparation[]
     */
    public function getReparations(): Collection
    {
        return $this->reparations;
    }

    public function addReparation(Reparation $reparation): self
    {
        if (!$this->reparations->contains($reparation)) {
            $this->reparations[] = $reparation;
            $reparation->setModel($this);
        }

        return $this;
    }

    public function removeReparation(Reparation $reparation): self
    {
        if ($this->reparations->removeElement($reparation)) {
            if ($reparation->getModel() === $this) {
                $reparation->setModel(null);
            }
        }

        return $this;
    }
}
