<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * Retourne les avis approuvés (pour affichage public).
     *
     * @return Review[]
     */
    public function findApproved(int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', Review::STATUS_APPROVED)
            ->orderBy('r.publishedAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Avis en attente de modération (pour admin).
     *
     * @return Review[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', Review::STATUS_PENDING)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Avis approuvés pour un article donné.
     *
     * @return Review[]
     */
    public function findApprovedForArticle(Article $article): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.article = :article')
            ->andWhere('r.status = :status')
            ->setParameter('article', $article)
            ->setParameter('status', Review::STATUS_APPROVED)
            ->orderBy('r.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Avis de l'utilisateur connecté (tous statuts).
     *
     * @return Review[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Note moyenne des avis approuvés (globale ou par article).
     */
    public function getAverageRating(?Article $article = null): ?float
    {
        $qb = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) AS avg_rating')
            ->andWhere('r.status = :status')
            ->setParameter('status', Review::STATUS_APPROVED);

        if ($article) {
            $qb->andWhere('r.article = :article')->setParameter('article', $article);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return $result ? round((float) $result, 1) : null;
    }

    /**
     * Distribution des notes (1 à 5) sur les avis approuvés
     */
    public function getRatingDistribution(?Article $article = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.rating, COUNT(r.id) AS nb')
            ->andWhere('r.status = :status')
            ->setParameter('status', Review::STATUS_APPROVED)
            ->groupBy('r.rating');

        if ($article) {
            $qb->andWhere('r.article = :article')->setParameter('article', $article);
        }

        $rows = $qb->getQuery()->getResult();

        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($rows as $row) {
            $r = (int) $row['rating'];
            if (isset($dist[$r])) {
                $dist[$r] = (int) $row['nb'];
            }
        }

        return $dist;
    }

    /**
     * Compteur avis en attente (pour badge admin)
     */
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', Review::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie si l'utilisateur a déjà laissé un avis pour cette commande (tous articles).
     * Utilisé pour la command d'invitation J+10 (on n'envoie plus d'email si au moins 1 avis).
     */
    public function hasUserReviewedCommande(User $user, int $commandeId): bool
    {
        return (bool) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :user')
            ->andWhere('r.commande = :commande')
            ->setParameter('user', $user)
            ->setParameter('commande', $commandeId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie si l'utilisateur a déjà noté cet article précis dans cette commande.
     * Permet à un client de noter plusieurs articles d'une même commande, mais une seule fois
     * par combinaison (user, commande, article).
     *
     * Passer $articleId = null vérifie l'existence d'un avis "général" (sans article lié)
     * sur cette commande.
     */
    public function hasUserReviewedArticleInCommande(User $user, int $commandeId, ?int $articleId): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :user')
            ->andWhere('r.commande = :commande')
            ->setParameter('user', $user)
            ->setParameter('commande', $commandeId);

        if ($articleId === null) {
            $qb->andWhere('r.article IS NULL');
        } else {
            $qb->andWhere('r.article = :article')
               ->setParameter('article', $articleId);
        }

        return (bool) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Retourne les IDs d'articles déjà notés par l'utilisateur pour une commande donnée.
     * @return int[]
     */
    public function getReviewedArticleIds(User $user, int $commandeId): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.article) AS article_id')
            ->andWhere('r.user = :user')
            ->andWhere('r.commande = :commande')
            ->andWhere('r.article IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('commande', $commandeId)
            ->getQuery()
            ->getArrayResult();

        return array_map('intval', array_column($rows, 'article_id'));
    }
}
