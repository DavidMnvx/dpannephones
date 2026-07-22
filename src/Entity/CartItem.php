<?php

// src/Entity/CartItem.php
namespace App\Entity;

use App\Entity\Cart;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private $cart;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false)]
    private $article;

    #[ORM\Column(type: 'integer')]
    private $quantity;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): self
    {
        $this->cart = $cart;

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

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    /**
     * Nom d'affichage de la ligne : nom de l'article + couleur + modèle éventuels.
     * Utilisé partout où la ligne est montrée au client (panier, Stripe, commandes).
     */
    public function getDisplayName(): string
    {
        $name = $this->article?->getName() ?? '';
        $opts = $this->options ?? [];
        if (!empty($opts['color'])) {
            $name .= ' — ' . $opts['color'];
        }
        if (!empty($opts['model'])) {
            $name .= ' (' . $opts['model'] . ')';
        }

        return $name;
    }

    public function setOptions(?array $options): static
    {
        $this->options = $options;

        return $this;
    }
}


