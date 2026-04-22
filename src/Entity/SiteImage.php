<?php

namespace App\Entity;

use App\Repository\SiteImageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Images modifiables depuis l'admin sans intervention technique.
 * Exemples : photo de la boutique sur la home, bannières, illustrations…
 *
 * Chaque image est identifiée par une clé unique (ex: 'home_shop_photo')
 * et récupérable dans les templates via la fonction Twig `site_image('clé')`.
 */
#[ORM\Entity(repositoryClass: SiteImageRepository::class)]
#[ORM\Table(name: 'site_image')]
class SiteImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Clé technique unique utilisée dans les templates.
     * Ex: 'home_shop_photo', 'contact_banner', etc.
     */
    #[ORM\Column(length: 80, unique: true)]
    private ?string $slug = null;

    /**
     * Libellé humain (affiché dans le CRUD admin).
     * Ex: "Photo de la boutique (page d'accueil)"
     */
    #[ORM\Column(length: 150)]
    private ?string $label = null;

    /**
     * Description/aide affichée à l'admin (format recommandé, dimensions…).
     */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    /**
     * Nom du fichier image uploadé (stocké dans public/uploads/images/).
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /**
     * Image par défaut (fallback si pas d'upload custom).
     * Chemin relatif depuis public/ — ex: 'images/accueil/boutique_accueil2.jpeg'
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultImage = null;

    /**
     * Catégorie d'affichage dans l'admin (ex: 'home', 'pages_principales', 'ordinateur'…).
     * Permet de regrouper les images par section dans /admin/images-site.
     */
    #[ORM\Column(length: 50)]
    private string $category = 'general';

    /**
     * Ordre d'affichage au sein de la catégorie (plus petit = plus haut).
     */
    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int { return $this->id; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $v): static { $this->slug = $v; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(string $v): static { $this->label = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $v): static
    {
        $this->image = $v;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getDefaultImage(): ?string { return $this->defaultImage; }
    public function setDefaultImage(?string $v): static { $this->defaultImage = $v; return $this; }

    public function getCategory(): string { return $this->category; }
    public function setCategory(string $v): static { $this->category = $v; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $v): static { $this->sortOrder = $v; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $v): static { $this->updatedAt = $v; return $this; }

    /**
     * Retourne le chemin web de l'image à afficher.
     * Si une image custom a été uploadée → chemin uploads.
     * Sinon → défaut.
     */
    public function getWebPath(): ?string
    {
        if ($this->image) {
            return 'uploads/images/' . $this->image;
        }
        return $this->defaultImage;
    }
}
