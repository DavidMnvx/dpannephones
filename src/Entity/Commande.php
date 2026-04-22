<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $total;

    #[ORM\Column(length: 20)]
    private string $statut = 'en_attente';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSessionId = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numeroSuivi = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $transporteur = null;

    /**
     * Code du mode de livraison choisi par le client (ex: 'colissimo', 'retrait').
     * Correspond à une clé dans App\Service\ShippingOptionsResolver::OPTIONS.
     */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $shippingMode = null;

    /**
     * Coût TTC de la livraison (0 pour retrait gratuit).
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $shippingCost = null;

    /**
     * Code promo appliqué (FK). SET NULL si le promo est supprimé ensuite.
     */
    #[ORM\ManyToOne(targetEntity: PromoCode::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PromoCode $promoCode = null;

    /**
     * Libellé du code promo utilisé (copie figée du code).
     * Conservé même si la promo est supprimée → traçabilité comptable.
     */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $promoCodeUsed = null;

    /**
     * Montant de la remise appliquée (TTC, en €).
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $discountAmount = null;

    #[ORM\OneToMany(targetEntity: CommandeItem::class, mappedBy: 'commande', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->items     = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }

    public function getTotal(): float { return $this->total; }
    public function setTotal(float $v): static { $this->total = $v; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): static { $this->statut = $v; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getStripeSessionId(): ?string { return $this->stripeSessionId; }
    public function setStripeSessionId(?string $v): static { $this->stripeSessionId = $v; return $this; }

    public function getNumeroSuivi(): ?string { return $this->numeroSuivi; }
    public function setNumeroSuivi(?string $v): static { $this->numeroSuivi = $v; return $this; }

    public function getTransporteur(): ?string { return $this->transporteur; }
    public function setTransporteur(?string $v): static { $this->transporteur = $v; return $this; }

    public function getShippingMode(): ?string { return $this->shippingMode; }
    public function setShippingMode(?string $v): static { $this->shippingMode = $v; return $this; }

    public function getShippingCost(): ?float { return $this->shippingCost !== null ? (float) $this->shippingCost : null; }
    public function setShippingCost(?float $v): static { $this->shippingCost = $v; return $this; }

    public function getPromoCode(): ?PromoCode { return $this->promoCode; }
    public function setPromoCode(?PromoCode $v): static { $this->promoCode = $v; return $this; }

    public function getPromoCodeUsed(): ?string { return $this->promoCodeUsed; }
    public function setPromoCodeUsed(?string $v): static { $this->promoCodeUsed = $v; return $this; }

    public function getDiscountAmount(): ?float
    {
        return $this->discountAmount !== null ? (float) $this->discountAmount : null;
    }
    public function setDiscountAmount(?float $v): static
    {
        $this->discountAmount = $v !== null ? (string) $v : null;
        return $this;
    }

    public function getItems(): Collection { return $this->items; }

    public function addItem(CommandeItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setCommande($this);
        }
        return $this;
    }
}
