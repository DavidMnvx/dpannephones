<?php

namespace App\Repository;

use App\Entity\AppSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppSetting>
 */
class AppSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppSetting::class);
    }

    public function findByKey(string $key): ?AppSetting
    {
        return $this->findOneBy(['key' => $key]);
    }

    /**
     * @return AppSetting[] Tous les settings groupés par catégorie, triés.
     */
    public function findAllGrouped(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.category', 'ASC')
            ->addOrderBy('s.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
