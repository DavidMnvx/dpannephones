<?php

namespace App\Entity;

use App\Repository\GoogleReviewRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Avis Google recopié manuellement par l'admin.
 * Affiché sur la page d'accueil à côté des avis clients du site.
 */
#[ORM\Entity(repositoryClass: GoogleReviewRepository::class)]
#[ORM\Table(name: 'google_review')]
class GoogleReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $authorName = null;

    /**
     * Note de 1 à 5
     */
    #[ORM\Column(type: 'smallint')]
    private ?int $rating = 5;

    /**
     * Texte affiché comme date (ex: "il y a 2 semaines")
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $relativeTime = null;

    #[ORM\Column(type: 'text')]
    private ?string $text = null;

    /**
     * URL de la photo de profil (optionnel — sinon initiales colorées)
     */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $profilePhotoUrl = null;

    /**
     * Visible sur le site ou non
     */
    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    /**
     * Ordre d'affichage (plus petit = affiché en premier)
     */
    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getAuthorName(): ?string { return $this->authorName; }
    public function setAuthorName(string $v): static { $this->authorName = $v; return $this; }

    public function getRating(): ?int { return $this->rating; }
    public function setRating(int $v): static { $this->rating = max(1, min(5, $v)); return $this; }

    public function getRelativeTime(): ?string { return $this->relativeTime; }
    public function setRelativeTime(?string $v): static { $this->relativeTime = $v; return $this; }

    public function getText(): ?string { return $this->text; }
    public function setText(string $v): static { $this->text = $v; return $this; }

    public function getProfilePhotoUrl(): ?string { return $this->profilePhotoUrl; }
    public function setProfilePhotoUrl(?string $v): static { $this->profilePhotoUrl = $v; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $v): static { $this->sortOrder = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }
}
