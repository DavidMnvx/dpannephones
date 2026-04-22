<?php

namespace App\Repository;

use App\Entity\GoogleReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoogleReview>
 */
class GoogleReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoogleReview::class);
    }

    /**
     * Retourne les avis actifs, ordonnés par sortOrder puis créations récentes.
     *
     * @return GoogleReview[]
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
