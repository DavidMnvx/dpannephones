<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catégorie d'articles de la boutique, gérable depuis l'admin.
 *
 * - slug          : identifiant technique stable (stocké dans article.categorie)
 * - label         : nom affiché (sidebar boutique, onglets admin, fil d'ariane…)
 * - icon          : classe FontAwesome affichée dans les menus (ex. "fas fa-gamepad")
 * - shippingTier  : niveau d'exigence livraison (1 petit colis → 4 très volumineux),
 *                   utilisé par ShippingOptionsResolver
 * - specsTemplate : quel groupe de champs techniques afficher dans le formulaire
 *                   article (pc_gamer, pc_portable, telephone…) — null : aucun
 * - position      : ordre d'affichage
 */
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'category')]
#[ORM\UniqueConstraint(name: 'category_slug_unique', columns: ['slug'])]
class Category
{
    public const SPECS_TEMPLATES = [
        'pc_gamer'       => 'PC Gamer',
        'pc_bureautique' => 'PC Bureautique',
        'pc_portable'    => 'PC Portable',
        'accessoires'    => 'Accessoire',
        'coque'          => 'Coque (choix de modèles)',
        'film_hydrogel'  => 'Film hydrogel (choix de modèles)',
        'telephone'      => 'Téléphone',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $slug = null;

    #[ORM\Column(length: 80)]
    private ?string $label = null;

    #[ORM\Column(length: 50)]
    private string $icon = 'fas fa-box';

    #[ORM\Column(name: 'shipping_tier')]
    private int $shippingTier = 1;

    #[ORM\Column(name: 'specs_template', length: 30, nullable: true)]
    private ?string $specsTemplate = null;

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }

    public function getIcon(): string { return $this->icon; }
    public function setIcon(string $icon): self { $this->icon = $icon; return $this; }

    public function getShippingTier(): int { return $this->shippingTier; }
    public function setShippingTier(int $tier): self { $this->shippingTier = max(1, min(4, $tier)); return $this; }

    public function getSpecsTemplate(): ?string { return $this->specsTemplate; }
    public function setSpecsTemplate(?string $t): self { $this->specsTemplate = $t ?: null; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
}
