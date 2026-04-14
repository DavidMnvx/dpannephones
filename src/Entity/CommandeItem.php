<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CommandeItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commande $commande = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Article $article = null;

    #[ORM\Column(length: 100)]
    private string $articleName = '';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $prix;

    #[ORM\Column(type: 'integer')]
    private int $quantite;

    public function getId(): ?int { return $this->id; }

    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $v): static { $this->commande = $v; return $this; }

    public function getArticle(): ?Article { return $this->article; }
    public function setArticle(?Article $v): static { $this->article = $v; return $this; }

    public function getArticleName(): string { return $this->articleName; }
    public function setArticleName(string $v): static { $this->articleName = $v; return $this; }

    public function getPrix(): float { return $this->prix; }
    public function setPrix(float $v): static { $this->prix = $v; return $this; }

    public function getQuantite(): int { return $this->quantite; }
    public function setQuantite(int $v): static { $this->quantite = $v; return $this; }

    public function getSousTotal(): float { return $this->prix * $this->quantite; }
}
