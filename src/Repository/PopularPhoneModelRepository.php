<?php

namespace App\Repository;

use App\Entity\PopularPhoneModel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PopularPhoneModel>
 */
class PopularPhoneModelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PopularPhoneModel::class);
    }

    /**
     * Retourne les modèles regroupés par marque, puis par famille.
     *
     * @return array<string, array{name: string, families: array<string, PopularPhoneModel[]>}>
     */
    public function findGroupedByBrandAndFamille(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->orderBy('m.brand', 'ASC')
            ->addOrderBy('m.famille', 'ASC')
            ->addOrderBy('m.sortOrder', 'ASC')
            ->addOrderBy('m.modelName', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($rows as $row) {
            $brand = $row->getBrand();
            $famille = $row->getFamille() ?: 'Autres';
            if (!isset($grouped[$brand])) {
                $grouped[$brand] = ['name' => $brand, 'families' => []];
            }
            if (!isset($grouped[$brand]['families'][$famille])) {
                $grouped[$brand]['families'][$famille] = [];
            }
            $grouped[$brand]['families'][$famille][] = $row;
        }
        return $grouped;
    }
}
