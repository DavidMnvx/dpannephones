<?php

namespace App\Repository;

use App\Entity\Partenaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Partenaire>
 */
class PartenaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partenaire::class);
    }

    /**
     * Retourne tous les partenaires actifs, triés par type (premium d'abord) puis sortOrder
     *
     * @return Partenaire[]
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active OR p.isActive IS NULL')
            ->setParameter('active', true)
            ->orderBy('p.type', 'ASC')      // premium < normal alphabétiquement donc premium en 1er
            ->addOrderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Partenaire[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.type = :type')
            ->andWhere('p.isActive = :active OR p.isActive IS NULL')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
