<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Commande;
use App\Entity\Review;
use App\Entity\User;
use App\Form\ReviewType;
use App\Repository\CommandeRepository;
use App\Repository\ReviewRepository;
use App\Service\ReviewNotificationService;
use App\Service\StaticReviewsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ReviewController extends AbstractController
{
    /**
     * Page publique — liste des avis approuvés + rappel avis Google
     */
    #[Route('/avis', name: 'reviews_index')]
    public function index(ReviewRepository $reviewRepo, StaticReviewsProvider $googleProvider): Response
    {
        $reviews            = $reviewRepo->findApproved();
        $averageRating      = $reviewRepo->getAverageRating();
        $ratingDistribution = $reviewRepo->getRatingDistribution();
        $total              = count($reviews);

        // Rappel Avis Google (max 3 les mieux notés)
        $googleReviews     = array_slice($googleProvider->getReviews(), 0, 3);
        $googleRating      = $googleProvider->getOverallRating();
        $googleTotal       = $googleProvider->getTotalRatings();

        return $this->render('reviews/index.html.twig', [
            'reviews'             => $reviews,
            'averageRating'       => $averageRating,
            'ratingDistribution'  => $ratingDistribution,
            'total'               => $total,
            'googleReviews'       => $googleReviews,
            'googleRating'        => $googleRating,
            'googleTotal'         => $googleTotal,
        ]);
    }

    /**
     * Formulaire — laisser un avis (connexion obligatoire).
     * Peut être lié à une commande précise via ?commande=X
     * Le client choisit ensuite quel article précis de la commande il souhaite noter
     * (ou laisser un avis général sur l'expérience).
     */
    #[Route('/avis/laisser', name: 'review_new')]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        CommandeRepository $commandeRepo,
        ReviewRepository $reviewRepo,
        ReviewNotificationService $notif
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Récupérer la commande si fournie
        $commandeId = $request->query->get('commande');
        $commande   = null;
        $commandeArticles  = [];  // articles dispo pour cette commande
        $reviewedArticleIds = [];
        $hasGeneralReview   = false;

        if ($commandeId) {
            $commande = $commandeRepo->find($commandeId);

            if (!$commande) {
                $this->addFlash('error', 'Commande introuvable.');
                return $this->redirectToRoute('account_index');
            }

            // La commande doit appartenir à l'utilisateur
            if ($commande->getUser() !== $user) {
                throw $this->createAccessDeniedException('Cette commande n\'est pas la vôtre.');
            }

            // Pas possible de noter une commande non finalisée
            if (!in_array($commande->getStatut(), ['payee', 'en_preparation', 'expediee', 'livree', 'retiree'], true)) {
                $this->addFlash('warning', 'Vous pourrez noter cette commande une fois qu\'elle aura été traitée.');
                return $this->redirectToRoute('account_index');
            }

            // Articles de la commande (uniques, liés à un vrai Article, pas supprimé)
            foreach ($commande->getItems() as $item) {
                $article = $item->getArticle();
                if ($article && !isset($commandeArticles[$article->getId()])) {
                    $commandeArticles[$article->getId()] = $article;
                }
            }
            $commandeArticles = array_values($commandeArticles);

            // Articles déjà notés par ce user pour cette commande + avis général existant
            $reviewedArticleIds = $reviewRepo->getReviewedArticleIds($user, $commande->getId());
            $hasGeneralReview   = $reviewRepo->hasUserReviewedArticleInCommande($user, $commande->getId(), null);

            // Si tout a déjà été noté, pas la peine d'afficher le form
            $allReviewed = $hasGeneralReview && count($reviewedArticleIds) === count($commandeArticles);
            if ($allReviewed && !empty($commandeArticles)) {
                $this->addFlash('info', 'Vous avez déjà noté tous les articles de cette commande. Merci !');
                return $this->redirectToRoute('account_index');
            }
        }

        $review = new Review();
        $review->setUser($user);
        $review->setCommande($commande);

        // Options du form : liste articles + IDs déjà notés + état "avis général"
        $form = $this->createForm(ReviewType::class, $review, [
            'commande_articles'   => $commandeArticles,
            'reviewed_article_ids'=> $reviewedArticleIds,
            'has_general_review'  => $hasGeneralReview,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Re-vérif anti-doublon (sécurité contre soumission directe)
            if ($commande) {
                $articleId = $review->getArticle()?->getId();
                if ($reviewRepo->hasUserReviewedArticleInCommande($user, $commande->getId(), $articleId)) {
                    $this->addFlash('warning', 'Vous avez déjà laissé un avis pour cet article sur cette commande.');
                    return $this->redirectToRoute('review_new', ['commande' => $commande->getId()]);
                }
            }

            $review->setStatus(Review::STATUS_PENDING);
            $em->persist($review);
            $em->flush();

            // Notification admin
            try {
                $notif->notifyAdminNewReview($review);
            } catch (\Exception $e) {
                // silencieux
            }

            $this->addFlash(
                'success',
                '🙏 Merci pour votre avis ! Il est bien enregistré et sera publié après validation par notre équipe (sous 24-48h). Vous pouvez suivre son statut dans "Mon compte > Mes avis".'
            );

            // Redirection vers la page d'accueil : le client voit un flash vert
            // bien visible qui confirme la réception de son avis.
            return $this->redirectToRoute('home');
        }

        return $this->render('reviews/new.html.twig', [
            'form'                => $form->createView(),
            'commande'            => $commande,
            'commandeArticles'    => $commandeArticles,
            'reviewedArticleIds'  => $reviewedArticleIds,
            'hasGeneralReview'    => $hasGeneralReview,
        ]);
    }

    /**
     * Mes avis (espace client)
     */
    #[Route('/mon-compte/avis', name: 'user_reviews')]
    #[IsGranted('ROLE_USER')]
    public function myReviews(ReviewRepository $reviewRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('reviews/my_reviews.html.twig', [
            'reviews' => $reviewRepo->findByUser($user),
        ]);
    }
}
