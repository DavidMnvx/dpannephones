<?php
namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class RegistrationEmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private string $mailerFrom,
        private string $mailerFromName,
    ) {}

    public function sendVerificationEmail(User $user, string $verifyRoute): void
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyRoute,
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()]
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFrom, $this->mailerFromName))
            ->to(new Address($user->getEmail(), $user->getPrenom() . ' ' . $user->getNom()))
            ->subject('✅ Confirmez votre adresse email — D\'Panne Phones')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'user'              => $user,
                'signedUrl'         => $signatureComponents->getSignedUrl(),
                'expiresAtMessageKey' => $signatureComponents->getExpirationMessageKey(),
                'expiresAtMessageData' => $signatureComponents->getExpirationMessageData(),
            ]);

        $this->mailer->send($email);
    }
}
