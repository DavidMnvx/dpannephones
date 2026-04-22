<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Avis client déposé sur le site après un achat.
 * Nécessite un compte utilisateur connecté + modération admin.
 */
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'review')]
#[ORM\HasLifecycleCallbacks]
class Review
{
    // Statuts de modération
    public const STATUS_PENDING  = 'pending';   // en attente de modération
    public const STATUS_APPROVED = 'approved';  // publié
    public const STATUS_REJECTED = 'rejected';  // refusé par admin

    // Format d'affichage du nom (choix du client)
    public const DISPLAY_FULL         = 'full';         // Prénom Nom complet
    public const DISPLAY_FIRSTNAME    = 'firstname';    // Prénom seul (Thomas)
    public const DISPLAY_INITIAL      = 'initial';      // Prénom + initiale (Thomas G.)
    public const DISPLAY_ANONYMOUS    = 'anonymous';    // Client anonyme

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * Commande associée (preuve d'achat pour badge "Vérifié")
     */
    #[ORM\ManyToOne(targetEntity: Commande::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Commande $commande = null;

    /**
     * Article spécifique noté (optionnel — si null, avis global boutique)
     */
    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Article $article = null;

    #[ORM\Column(type: 'smallint')]
    private ?int $rating = 5;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    private ?string $comment = null;

    /**
     * Comment afficher le nom de l'auteur : full / firstname / initial / anonymous
     */
    #[ORM\Column(length: 20)]
    private string $displayNameChoice = self::DISPLAY_INITIAL;

    /**
     * Statut de modération : pending / approved / rejected
     */
    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    /**
     * Réponse de l'admin à l'avis (optionnelle, publique)
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adminResponse = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $publishedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $adminResponseAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $v): static { $this->user = $v; return $this; }

    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $v): static { $this->commande = $v; return $this; }

    public function getArticle(): ?Article { return $this->article; }
    public function setArticle(?Article $v): static { $this->article = $v; return $this; }

    public function getRating(): ?int { return $this->rating; }
    public function setRating(int $v): static { $this->rating = max(1, min(5, $v)); return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(string $v): static { $this->comment = $v; return $this; }

    public function getDisplayNameChoice(): string { return $this->displayNameChoice; }
    public function setDisplayNameChoice(string $v): static { $this->displayNameChoice = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static
    {
        $this->status = $v;
        if ($v === self::STATUS_APPROVED && !$this->publishedAt) {
            $this->publishedAt = new \DateTime();
        }
        return $this;
    }

    public function getAdminResponse(): ?string { return $this->adminResponse; }
    public function setAdminResponse(?string $v): static
    {
        $this->adminResponse = $v;
        if ($v && !$this->adminResponseAt) {
            $this->adminResponseAt = new \DateTime();
        }
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }

    public function getPublishedAt(): ?\DateTimeInterface { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeInterface $v): static { $this->publishedAt = $v; return $this; }

    public function getAdminResponseAt(): ?\DateTimeInterface { return $this->adminResponseAt; }

    // ──────────────────────────────────────
    // Méthodes utilitaires
    // ──────────────────────────────────────

    public function isApproved(): bool  { return $this->status === self::STATUS_APPROVED; }
    public function isPending(): bool   { return $this->status === self::STATUS_PENDING; }
    public function isRejected(): bool  { return $this->status === self::STATUS_REJECTED; }
    public function isVerified(): bool  { return $this->commande !== null; }

    /**
     * Retourne le nom affiché selon le choix du client.
     */
    public function getDisplayName(): string
    {
        if (!$this->user) {
            return 'Client';
        }

        $prenom = $this->user->getPrenom() ?? '';
        $nom    = $this->user->getNom() ?? '';

        return match ($this->displayNameChoice) {
            self::DISPLAY_FULL       => trim($prenom . ' ' . $nom),
            self::DISPLAY_INITIAL    => trim($prenom . ' ' . ($nom !== '' ? mb_substr($nom, 0, 1) . '.' : '')),
            self::DISPLAY_ANONYMOUS  => 'Client vérifié',
            default /* FIRSTNAME */  => $prenom ?: 'Client',
        };
    }
}
