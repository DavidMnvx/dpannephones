<?php

namespace App\Entity;

use App\Repository\PromoCodeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Code promo gérable depuis l'admin.
 *
 * Deux types :
 *  - 'fixed'      : remise fixe en € (ex: -10€)
 *  - 'percentage' : remise en % (ex: -10%)
 *
 * Règles d'application :
 *  - isActive doit être true
 *  - validFrom / validUntil définissent une fenêtre (null = pas de limite)
 *  - usageLimit vs usageCount (null = illimité)
 *  - minCartAmount : montant minimum du panier pour activer (null = pas de minimum)
 */
#[ORM\Entity(repositoryClass: PromoCodeRepository::class)]
#[ORM\Table(name: 'promo_code')]
#[ORM\UniqueConstraint(name: 'promo_code_unique', columns: ['code'])]
class PromoCode
{
    public const TYPE_FIXED      = 'fixed';
    public const TYPE_PERCENTAGE = 'percentage';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Code tel que saisi par le client (auto-uppercasé). Ex: "BIENVENUE10" */
    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    /** 'fixed' ou 'percentage' */
    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_PERCENTAGE;

    /** Valeur : montant en € si fixed, pourcentage si percentage */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $value = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $validUntil = null;

    /** Nombre max d'utilisations totales (null = illimité) */
    #[ORM\Column(nullable: true)]
    private ?int $usageLimit = null;

    /** Compteur d'utilisations — incrémenté après paiement confirmé */
    #[ORM\Column]
    private int $usageCount = 0;

    /** Montant minimum du panier (TTC) pour que le code soit valide */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $minCartAmount = null;

    /** Note interne admin (ex: "Campagne newsletter juin 2026") */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // ─── Getters / Setters ─────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $code): static
    {
        // Normalisation : toujours en majuscules, sans espaces
        $this->code = $code !== null ? strtoupper(trim($code)) : null;
        return $this;
    }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static
    {
        if (!in_array($type, [self::TYPE_FIXED, self::TYPE_PERCENTAGE], true)) {
            throw new \InvalidArgumentException("Type invalide : $type");
        }
        $this->type = $type;
        return $this;
    }

    public function getValue(): ?float { return $this->value !== null ? (float) $this->value : null; }
    public function setValue(?float $value): static
    {
        $this->value = $value !== null ? (string) $value : null;
        return $this;
    }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }

    public function getValidFrom(): ?\DateTimeInterface { return $this->validFrom; }
    public function setValidFrom(?\DateTimeInterface $v): static { $this->validFrom = $v; return $this; }

    public function getValidUntil(): ?\DateTimeInterface { return $this->validUntil; }
    public function setValidUntil(?\DateTimeInterface $v): static { $this->validUntil = $v; return $this; }

    public function getUsageLimit(): ?int { return $this->usageLimit; }
    public function setUsageLimit(?int $v): static { $this->usageLimit = $v; return $this; }

    public function getUsageCount(): int { return $this->usageCount; }
    public function setUsageCount(int $v): static { $this->usageCount = $v; return $this; }
    public function incrementUsage(): static { $this->usageCount++; return $this; }

    public function getMinCartAmount(): ?float
    {
        return $this->minCartAmount !== null ? (float) $this->minCartAmount : null;
    }
    public function setMinCartAmount(?float $v): static
    {
        $this->minCartAmount = $v !== null ? (string) $v : null;
        return $this;
    }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    // ─── Logique métier ─────────────────────────────────────────────

    /**
     * Calcule la remise à appliquer sur un total TTC donné.
     * Retourne un montant en € (toujours >= 0, jamais supérieur au total).
     */
    public function computeDiscount(float $cartTotal): float
    {
        $value = $this->getValue() ?? 0;

        if ($this->type === self::TYPE_FIXED) {
            $discount = $value;
        } else { // percentage
            $discount = $cartTotal * ($value / 100);
        }

        // On plafonne la remise au total du panier (pas de remboursement)
        $discount = min($discount, $cartTotal);
        $discount = max($discount, 0);

        return round($discount, 2);
    }

    /**
     * Vérifie l'état intrinsèque du code (dates, actif, limite).
     * Ne prend PAS en compte le panier (voir PromoCodeRepository::findValidByCode).
     */
    public function isCurrentlyValid(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $now = new \DateTime();

        if ($this->validFrom && $now < $this->validFrom) {
            return false;
        }

        if ($this->validUntil && $now > $this->validUntil) {
            return false;
        }

        if ($this->usageLimit !== null && $this->usageCount >= $this->usageLimit) {
            return false;
        }

        return true;
    }

    /**
     * Libellé affichable (ex: "-10€" ou "-10%")
     */
    public function getDisplayValue(): string
    {
        $v = $this->getValue() ?? 0;
        if ($this->type === self::TYPE_FIXED) {
            return '-' . number_format($v, 2, ',', ' ') . ' €';
        }
        return '-' . rtrim(rtrim(number_format($v, 2, ',', ' '), '0'), ',') . ' %';
    }
}
