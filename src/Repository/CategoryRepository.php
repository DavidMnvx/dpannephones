<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    /** Cache par requête HTTP : les catégories sont lues à chaque page boutique */
    private ?array $orderedCache = null;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /** @return Category[] triées par position */
    public function findAllOrdered(): array
    {
        return $this->orderedCache ??= $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    /** Map slug => label, dans l'ordre d'affichage (remplace l'ancienne const CATEGORIES) */
    public function getSlugLabelMap(): array
    {
        $map = [];
        foreach ($this->findAllOrdered() as $c) {
            $map[$c->getSlug()] = $c->getLabel();
        }
        return $map;
    }

    /** Map slug => icône FontAwesome */
    public function getSlugIconMap(): array
    {
        $map = [];
        foreach ($this->findAllOrdered() as $c) {
            $map[$c->getSlug()] = $c->getIcon();
        }
        return $map;
    }

    /** Map slug => gabarit de specs (pour le JS des formulaires admin) */
    public function getSlugSpecsTemplateMap(): array
    {
        $map = [];
        foreach ($this->findAllOrdered() as $c) {
            if ($c->getSpecsTemplate()) {
                $map[$c->getSlug()] = $c->getSpecsTemplate();
            }
        }
        return $map;
    }

    /** Map slug => tier de livraison */
    /** slug => famille (null si la catégorie est seule) */
    public function getSlugFamilyMap(): array
    {
        $map = [];
        foreach ($this->findAllOrdered() as $c) {
            $map[$c->getSlug()] = $c->getFamily();
        }
        return $map;
    }

    /** Familles existantes (dédoublonnées, ordre d'apparition) — pour l'autocomplétion admin */
    public function getFamilies(): array
    {
        $out = [];
        foreach ($this->findAllOrdered() as $c) {
            if ($c->getFamily() && !in_array($c->getFamily(), $out, true)) {
                $out[] = $c->getFamily();
            }
        }
        return $out;
    }

    public function getSlugTierMap(): array
    {
        $map = [];
        foreach ($this->findAllOrdered() as $c) {
            $map[$c->getSlug()] = $c->getShippingTier();
        }
        return $map;
    }
}
