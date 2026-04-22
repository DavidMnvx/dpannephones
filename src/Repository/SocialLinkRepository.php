<?php

namespace App\Repository;

use App\Entity\SocialLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SocialLink>
 */
class SocialLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialLink::class);
    }

    /**
     * @return SocialLink[] Liens actifs triés par ordre d'affichage.
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = :a')
            ->setParameter('a', true)
            ->orderBy('s.sortOrder', 'ASC')
            ->addOrderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
