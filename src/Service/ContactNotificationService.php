<?php

namespace App\Service;

use App\Entity\Contact;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Envoie les messages du formulaire de contact au support D'Panne Phones.
 */
class ContactNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment     $twig,
        private LoggerInterface $logger,
        private string          $mailerFrom,
        private string          $mailerFromName,
        private string          $adminEmail,
    ) {}

    public function sendContactMessage(Contact $contact): void
    {
        $this->logger->info('[Contact] Envoi message support', [
            'from' => $contact->getEmail(),
            'to'   => $this->adminEmail,
        ]);

        try {
            $html = $this->twig->render('email/contact_support.html.twig', [
                'contact' => $contact,
                'sentAt'  => new \DateTime(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[Contact] Erreur Twig : ' . $e->getMessage());
            throw $e;
        }

        $subject = sprintf(
            '📩 Nouveau message — %s %s',
            $contact->getFirstName(),
            $contact->getLastName()
        );

        $email = (new Email())
            // From = adresse technique de l'app (sinon SPF/DKIM ne passent pas)
            ->from(sprintf('%s <%s>', $this->mailerFromName, $this->mailerFrom))
            // Reply-To = l'email du client (pour répondre directement)
            ->replyTo($contact->getEmail())
            ->to($this->adminEmail)
            ->subject($subject)
            ->html($html);

        try {
            $this->mailer->send($email);
            $this->logger->info('[Contact] Email envoyé au support ' . $this->adminEmail);
        } catch (\Exception $e) {
            $this->logger->error('[Contact] Erreur envoi SMTP : ' . $e->getMessage());
            throw $e;
        }
    }
}
