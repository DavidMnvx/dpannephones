<?php

namespace App\Service;

use App\Entity\Review;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Notifications email liées aux avis clients.
 * - Email admin à chaque nouvel avis à modérer
 * - Email client quand son avis est approuvé
 * - Email J+X "Laissez un avis" après une commande
 */
class ReviewNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment     $twig,
        private LoggerInterface $logger,
        private string          $mailerFrom,
        private string          $mailerFromName,
        private string          $adminEmail,
    ) {}

    /**
     * Notifie l'admin qu'un nouvel avis est à modérer.
     */
    public function notifyAdminNewReview(Review $review): void
    {
        try {
            $html = $this->twig->render('email/admin_new_review.html.twig', [
                'review' => $review,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[Review Notif] Erreur Twig admin : ' . $e->getMessage());
            return;
        }

        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->mailerFromName, $this->mailerFrom))
            ->to($this->adminEmail)
            ->subject(sprintf('📝 Nouvel avis à modérer — %d★ de %s', $review->getRating(), $review->getDisplayName()))
            ->html($html);

        if ($review->getUser() && $review->getUser()->getEmail()) {
            $email->replyTo($review->getUser()->getEmail());
        }

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('[Review Notif] Erreur SMTP admin : ' . $e->getMessage());
        }
    }

    /**
     * Notifie le client que son avis a été publié.
     */
    public function notifyUserReviewApproved(Review $review): void
    {
        if (!$review->getUser() || !$review->getUser()->getEmail()) {
            return;
        }

        try {
            $html = $this->twig->render('email/user_review_approved.html.twig', [
                'review' => $review,
                'user'   => $review->getUser(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[Review Notif] Erreur Twig approuvé : ' . $e->getMessage());
            return;
        }

        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->mailerFromName, $this->mailerFrom))
            ->to($review->getUser()->getEmail())
            ->subject('✅ Votre avis a été publié — Merci ! — D\'Panne Phones')
            ->html($html);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('[Review Notif] Erreur SMTP user : ' . $e->getMessage());
        }
    }

    /**
     * Envoie une relance "Laissez votre avis" à un client après commande.
     */
    public function sendReviewInvitation(\App\Entity\Commande $commande): void
    {
        $user = $commande->getUser();
        if (!$user || !$user->getEmail()) {
            return;
        }

        try {
            $html = $this->twig->render('email/user_review_invitation.html.twig', [
                'commande' => $commande,
                'user'     => $user,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[Review Notif] Erreur Twig invitation : ' . $e->getMessage());
            return;
        }

        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->mailerFromName, $this->mailerFrom))
            ->to($user->getEmail())
            ->subject('💬 Comment s\'est passée votre commande ? — D\'Panne Phones')
            ->html($html);

        try {
            $this->mailer->send($email);
            $this->logger->info('[Review Notif] Invitation envoyée pour commande #' . $commande->getId());
        } catch (\Exception $e) {
            $this->logger->error('[Review Notif] Erreur SMTP invitation : ' . $e->getMessage());
        }
    }
}
