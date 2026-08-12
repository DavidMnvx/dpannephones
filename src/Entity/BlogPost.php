<?php

namespace App\Entity;

use App\Repository\BlogPostRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Article de la rubrique « Conseils & Actualités ».
 *
 * Cycle de vie : créé en brouillon (par l'admin ou via l'API de dépôt
 * /api/blog/drafts pour les agents IA), relu puis publié depuis le back-office.
 * Seuls les articles publiés sont visibles sur le site et dans le sitemap.
 */
#[ORM\Entity(repositoryClass: BlogPostRepository::class)]
#[ORM\Table(name: 'blog_post')]
class BlogPost
{
    /** Thèmes éditoriaux proposés dans l'admin (valeur => libellé affiché) */
    public const TOPICS = [
        'reparation'   => 'Réparation & réparabilité',
        'telephones'   => 'Smartphones & téléphones',
        'pc'           => 'PC & informatique',
        'gaming'       => 'PC Gaming',
        'batteries'    => 'Batteries & entretien',
        'securite'     => 'Sécurité informatique',
        'occasion'     => 'Occasion & reconditionné',
        'local'        => 'Conseils Pélissanne & alentours',
        'tendances'    => 'Tendances & nouveautés',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    /** URL personnalisée : /conseils/{slug} */
    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $topic = null;

    /** Chapo affiché sur la carte de la liste (sinon début du contenu) */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $excerpt = null;

    /** Contenu structuré : paragraphes, "## Sous-titre", listes "* ", liens [texte](url) */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    /** Image de couverture (fichier dans uploads/images/) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /** Balise <title> ; à défaut le titre de l'article est utilisé */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoTitle = null;

    #[ORM\Column(length: 300, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPublished = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /** Provenance du brouillon : null = saisie admin, sinon nom de l'agent (API) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $source = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getTopic(): ?string { return $this->topic; }
    public function setTopic(?string $topic): static { $this->topic = $topic; return $this; }

    public function getTopicLabel(): ?string
    {
        return $this->topic ? (self::TOPICS[$this->topic] ?? $this->topic) : null;
    }

    public function getExcerpt(): ?string { return $this->excerpt; }
    public function setExcerpt(?string $excerpt): static { $this->excerpt = $excerpt; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }

    public function getSeoTitle(): ?string { return $this->seoTitle; }
    public function setSeoTitle(?string $seoTitle): static { $this->seoTitle = $seoTitle; return $this; }

    public function getMetaDescription(): ?string { return $this->metaDescription; }
    public function setMetaDescription(?string $metaDescription): static { $this->metaDescription = $metaDescription; return $this; }

    public function isPublished(): bool { return $this->isPublished; }

    public function setIsPublished(bool $isPublished): static
    {
        // Première publication : on fige la date (conservée si on repasse en brouillon)
        if ($isPublished && !$this->isPublished && $this->publishedAt === null) {
            $this->publishedAt = new \DateTime();
        }
        $this->isPublished = $isPublished;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeInterface { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeInterface $publishedAt): static { $this->publishedAt = $publishedAt; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function touch(): static { $this->updatedAt = new \DateTime(); return $this; }

    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $source): static { $this->source = $source; return $this; }
}
