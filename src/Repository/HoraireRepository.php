<?php

namespace App\Repository;

use App\Entity\Horaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Horaire>
 */
class HoraireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Horaire::class);
    }

    /**
     * @return Horaire[]
     */
    public function findAllOrderedByJour(): array
    {
        return $this->createQueryBuilder('h')
            ->orderBy('h.jourNumero', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
