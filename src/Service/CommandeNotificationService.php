<?php

namespace App\Service;

use App\Entity\Commande;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class CommandeNotificationService
{
    // Mapping statut → sujet email
    private const SUBJECTS = [
        'payee'          => '✅ Commande confirmée — D\'Panne Phones',
        'en_preparation' => '🔧 Votre commande est en préparation — D\'Panne Phones',
        'expediee'       => '🚚 Votre commande a été expédiée — D\'Panne Phones',
        'livree'         => '📦 Votre commande est livrée — D\'Panne Phones',
        'retiree'        => '🏪 Retrait en boutique confirmé — D\'Panne Phones',
        'annulee'        => '❌ Commande annulée — D\'Panne Phones',
    ];

    // Statuts qui déclenchent un email
    private const STATUTS_NOTIFIES = ['payee', 'en_preparation', 'expediee', 'livree', 'retiree', 'annulee'];

    public function __construct(
        private MailerInterface $mailer,
        private Environment     $twig,
        private string          $mailerFrom,
        private string          $mailerFromName,
        private LoggerInterface $logger,
        private string          $adminEmail,
    ) {}

    public function notifyStatutChange(Commande $commande): void
    {
        $statut = $commande->getStatut();

        // Pas d'email pour ce statut
        if (!in_array($statut, self::STATUTS_NOTIFIES, true)) {
            return;
        }

        // Pas de client associé
        $user = $commande->getUser();
        if (!$user) {
            return;
        }

        $subject = self::SUBJECTS[$statut] ?? 'Mise à jour de votre commande — D\'Panne Phones';

        $this->logger->info('[Notif] Tentative envoi email', [
            'commande' => $commande->getId(),
            'statut'   => $statut,
            'to'       => $user->getEmail(),
            'from'     => $this->mailerFrom,
        ]);

        try {
            $html = $this->twig->render('email/commande_statut.html.twig', [
                'commande' => $commande,
                'user'     => $user,
                'statut'   => $statut,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[Notif] Erreur Twig : ' . $e->getMessage());
            throw $e;
        }

        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->mailerFromName, $this->mailerFrom))
            ->to($user->getEmail())
            ->subject($subject)
            ->html($html);

        try {
            $this->mailer->send($email);
            $this->logger->info('[Notif] Email envoyé avec succès à ' . $user->getEmail());
        } catch (\Exception $e) {
            $this->logger->error('[Notif] Erreur envoi SMTP : ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Envoie un email récapitulatif à l'admin pour chaque nouvelle commande payée.
     * Appelé au moment du paiement réussi (PaymentController::success + webhook).
     */
    public function notifyAdminNewCommande(Commande $commande): void
    {
        $this->logger->info('[Notif Admin] Nouvelle commande', [
            'commande' => $commande->getId(),
            'to'       => $this->adminEmail,
            'total'    => $commande->getTotal(),
        ]);

        try {
            $html = $this->twig->render('email/admin_new_commande.html.twig', [
                'commande' => $commande,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[Notif Admin] Erreur Twig : ' . $e->getMessage());
            throw $e;
        }

        $subject = sprintf(
            '🛒 Nouvelle commande #%d — %s €',
            $commande->getId(),
            number_format($commande->getTotal(), 2, ',', ' ')
        );

        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->mailerFromName, $this->mailerFrom))
            ->to($this->adminEmail)
            ->subject($subject)
            ->html($html);

        // Reply-To = email du client si on en a un
        if ($commande->getUser() && $commande->getUser()->getEmail()) {
            $email->replyTo($commande->getUser()->getEmail());
        }

        try {
            $this->mailer->send($email);
            $this->logger->info('[Notif Admin] Email envoyé au support ' . $this->adminEmail);
        } catch (\Exception $e) {
            $this->logger->error('[Notif Admin] Erreur envoi SMTP : ' . $e->getMessage());
            // On ne re-throw pas — l'échec de l'email admin ne doit pas casser la commande
        }
    }
}
