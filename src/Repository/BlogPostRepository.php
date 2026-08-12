<?php

namespace App\Repository;

use App\Entity\BlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogPost>
 */
class BlogPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogPost::class);
    }

    /**
     * Articles publiés, les plus récents en premier.
     *
     * @return BlogPost[]
     */
    public function findPublished(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isPublished = :vrai')->setParameter('vrai', true)
            ->orderBy('p.publishedAt', 'DESC')
            ->addOrderBy('p.id', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Articles publiés avec recherche plein-texte simple et tri.
     * Tri : recents (défaut) | anciens | alpha.
     *
     * @return BlogPost[]
     */
    public function findPublishedFiltered(string $search = '', ?string $sort = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isPublished = :vrai')->setParameter('vrai', true);

        if ($search !== '') {
            $qb->andWhere('p.title LIKE :q OR p.excerpt LIKE :q OR p.content LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        match ($sort) {
            'anciens' => $qb->orderBy('p.publishedAt', 'ASC')->addOrderBy('p.id', 'ASC'),
            'alpha'   => $qb->orderBy('p.title', 'ASC'),
            default   => $qb->orderBy('p.publishedAt', 'DESC')->addOrderBy('p.id', 'DESC'),
        };

        return $qb->getQuery()->getResult();
    }

    public function findOnePublishedBySlug(string $slug): ?BlogPost
    {
        return $this->createQueryBuilder('p')
            ->where('p.slug = :slug')->setParameter('slug', $slug)
            ->andWhere('p.isPublished = :vrai')->setParameter('vrai', true)
            ->getQuery()->getOneOrNullResult();
    }
}
