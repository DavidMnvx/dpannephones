<?php

namespace App\Entity;

use App\Repository\AppSettingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paramètre global du site modifiable depuis l'admin.
 *
 * Stockage key-value typé :
 *  - key    : identifiant technique (ex: 'review_reward_enabled')
 *  - value  : valeur sérialisée en string
 *  - type   : 'bool' | 'int' | 'float' | 'string'
 *
 * Métadonnées d'affichage :
 *  - label       : libellé humain (ex: "Activer les récompenses avis")
 *  - description : aide contextuelle
 *  - category    : section dans l'UI (ex: "Récompense avis")
 *  - sortOrder   : ordre d'affichage dans la catégorie
 */
#[ORM\Entity(repositoryClass: AppSettingRepository::class)]
#[ORM\Table(name: 'app_setting')]
#[ORM\UniqueConstraint(name: 'app_setting_key_unique', columns: ['setting_key'])]
class AppSetting
{
    public const TYPE_BOOL   = 'bool';
    public const TYPE_INT    = 'int';
    public const TYPE_FLOAT  = 'float';
    public const TYPE_STRING = 'string';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** 'key' est un mot réservé MySQL → colonne renommée 'setting_key' */
    #[ORM\Column(name: 'setting_key', length: 80, unique: true)]
    private ?string $key = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_STRING;

    #[ORM\Column(length: 150)]
    private ?string $label = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 80)]
    private string $category = 'general';

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // ─── Getters / Setters ─────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getKey(): ?string { return $this->key; }
    public function setKey(string $v): static { $this->key = $v; return $this; }

    public function getRawValue(): ?string { return $this->value; }
    public function setRawValue(?string $v): static
    {
        $this->value = $v;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getType(): string { return $this->type; }
    public function setType(string $v): static
    {
        if (!in_array($v, [self::TYPE_BOOL, self::TYPE_INT, self::TYPE_FLOAT, self::TYPE_STRING], true)) {
            throw new \InvalidArgumentException("Type invalide : $v");
        }
        $this->type = $v;
        return $this;
    }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(string $v): static { $this->label = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function getCategory(): string { return $this->category; }
    public function setCategory(string $v): static { $this->category = $v; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $v): static { $this->sortOrder = $v; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }

    // ─── Casting typé ─────────────────────────────────────

    /**
     * Retourne la valeur castée selon le type déclaré.
     */
    public function getTypedValue(): bool|int|float|string|null
    {
        if ($this->value === null || $this->value === '') {
            return match ($this->type) {
                self::TYPE_BOOL   => false,
                self::TYPE_INT    => 0,
                self::TYPE_FLOAT  => 0.0,
                self::TYPE_STRING => '',
            };
        }

        return match ($this->type) {
            self::TYPE_BOOL   => in_array(strtolower($this->value), ['1', 'true', 'yes', 'on'], true),
            self::TYPE_INT    => (int) $this->value,
            self::TYPE_FLOAT  => (float) $this->value,
            self::TYPE_STRING => (string) $this->value,
        };
    }

    /**
     * Setter typé : convertit n'importe quelle valeur en string pour stockage.
     */
    public function setTypedValue(bool|int|float|string|null $v): static
    {
        if ($v === null) {
            $this->value = null;
        } elseif (is_bool($v)) {
            $this->value = $v ? '1' : '0';
        } else {
            $this->value = (string) $v;
        }
        $this->updatedAt = new \DateTime();
        return $this;
    }
}
