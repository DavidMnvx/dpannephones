<?php

namespace App\Repository;

use App\Entity\Reparation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reparation>
 */
class ReparationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reparation::class);
    }

    /**
     * Toutes les réparations triées : par marque → modèle → ordre admin → nom.
     * Idéal pour la liste admin et la liste publique.
     *
     * @return Reparation[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.model', 'm')
            ->leftJoin('m.marque', 'mq')
            ->addSelect('m', 'mq')
            ->orderBy('mq.name', 'ASC')
            ->addOrderBy('m.name', 'ASC')
            ->addOrderBy('r.sortOrder', 'ASC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Réparations groupées par modèle (clé = id du modèle).
     * Pratique pour afficher en admin avec sous-titres par modèle.
     *
     * @return array<int, array{model: Model, items: Reparation[]}>
     */
    public function findGroupedByModel(): array
    {
        $reparations = $this->findAllOrdered();
        $grouped = [];

        foreach ($reparations as $rep) {
            $model = $rep->getModel();
            if (!$model) continue;

            $modelId = $model->getId();
            if (!isset($grouped[$modelId])) {
                $grouped[$modelId] = [
                    'model' => $model,
                    'items' => [],
                ];
            }
            $grouped[$modelId]['items'][] = $rep;
        }

        return $grouped;
    }
}
