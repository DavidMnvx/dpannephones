<?php

namespace App\Entity;

use App\Repository\PhotoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
#[ORM\Table(name: 'photo')]
class Photo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $filename = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Article $article = null;

    /**
     * Couleur associée à cette photo (nullable = photo générique, visible pour toutes les couleurs).
     * Permet de gérer un article multi-couleurs avec ses photos par variante.
     */
    #[ORM\ManyToOne(targetEntity: ProductColor::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProductColor $color = null;

    public function getColor(): ?ProductColor
    {
        return $this->color;
    }

    public function setColor(?ProductColor $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): self
    {
        $this->article = $article;
        return $this;
    }
}
