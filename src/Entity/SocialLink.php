<?php

namespace App\Entity;

use App\Repository\SocialLinkRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lien vers un réseau social, affiché sur :
 *  - Footer du site
 *  - Page /contact
 *  - Page /qui-sommes-nous
 *
 * Gérable depuis /admin/reseaux-sociaux.
 */
#[ORM\Entity(repositoryClass: SocialLinkRepository::class)]
#[ORM\Table(name: 'social_link')]
class SocialLink
{
    /**
     * Plateformes supportées avec métadonnées d'affichage.
     * Ajouter ici une entrée permet d'ouvrir une nouvelle plateforme dans le CRUD.
     */
    public const PLATFORMS = [
        'facebook'  => ['label' => 'Facebook',    'icon' => 'fab fa-facebook-f',    'color' => '#1877F2'],
        'instagram' => ['label' => 'Instagram',   'icon' => 'fab fa-instagram',     'color' => '#E1306C'],
        'tiktok'    => ['label' => 'TikTok',      'icon' => 'fab fa-tiktok',        'color' => '#000000'],
        'twitter'   => ['label' => 'X (Twitter)', 'icon' => 'fab fa-twitter',       'color' => '#000000'],
        'youtube'   => ['label' => 'YouTube',     'icon' => 'fab fa-youtube',       'color' => '#FF0000'],
        'linkedin'  => ['label' => 'LinkedIn',    'icon' => 'fab fa-linkedin-in',   'color' => '#0A66C2'],
        'snapchat'  => ['label' => 'Snapchat',    'icon' => 'fab fa-snapchat-ghost','color' => '#FFFC00'],
        'pinterest' => ['label' => 'Pinterest',   'icon' => 'fab fa-pinterest-p',   'color' => '#E60023'],
        'whatsapp'  => ['label' => 'WhatsApp',    'icon' => 'fab fa-whatsapp',      'color' => '#25D366'],
        'telegram'  => ['label' => 'Telegram',    'icon' => 'fab fa-telegram-plane','color' => '#26A5E4'],
        'other'     => ['label' => 'Autre',       'icon' => 'fas fa-link',          'color' => '#64748b'],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Clé de PLATFORMS — ex: 'facebook', 'instagram'… */
    #[ORM\Column(length: 30)]
    private ?string $platform = null;

    /** URL complète — ex: 'https://www.facebook.com/dpannephones' */
    #[ORM\Column(length: 500)]
    private ?string $url = null;

    /** Libellé personnalisé (optionnel — par défaut : nom de la plateforme) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $label = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // ─── Getters / Setters ─────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getPlatform(): ?string { return $this->platform; }
    public function setPlatform(string $v): static
    {
        if (!isset(self::PLATFORMS[$v])) {
            throw new \InvalidArgumentException("Plateforme inconnue : $v");
        }
        $this->platform = $v;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(string $v): static
    {
        $this->url = $v;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $v): static
    {
        $this->label = $v;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static
    {
        $this->isActive = $v;
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $v): static { $this->sortOrder = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }

    // ─── Helpers d'affichage ──────────────────────

    public function getDisplayLabel(): string
    {
        return $this->label ?: (self::PLATFORMS[$this->platform]['label'] ?? ucfirst($this->platform));
    }

    public function getIcon(): string
    {
        return self::PLATFORMS[$this->platform]['icon'] ?? 'fas fa-link';
    }

    public function getColor(): string
    {
        return self::PLATFORMS[$this->platform]['color'] ?? '#64748b';
    }
}
