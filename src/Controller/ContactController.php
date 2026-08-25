<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use App\Service\ContactNotificationService;
use App\Service\TurnstileVerifier;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact')]
    public function index(
        Request $request,
        ContactNotificationService $contactService,
        TurnstileVerifier $turnstile,
        #[Autowire(service: 'limiter.contact_submit')]
        RateLimiterFactory $contactSubmitLimiter,
        LoggerInterface $logger
    ): Response {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ─── 1. Honeypot : si le champ caché est rempli, c'est un bot ───
            if (!empty(trim((string) $request->request->get('_website')))) {
                $logger->info('[Contact] Honeypot rempli — soumission bot rejetée', [
                    'ip' => $request->getClientIp(),
                ]);
                // On retourne la même réponse qu'un succès pour ne pas donner d'info au bot
                $this->addFlash('success', 'Votre message est bien parti — on vous répond sous 24 à 48 h.');
                return $this->redirectToRoute('contact');
            }

            // ─── 2. Rate limiter : max 3 envois / IP / heure ───
            $limiter = $contactSubmitLimiter->create($request->getClientIp() ?? 'unknown');
            $limit = $limiter->consume(1);
            if (!$limit->isAccepted()) {
                $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();
                $minutes = max(1, (int) ceil($retryAfter / 60));
                $this->addFlash('error', sprintf(
                    'Trop de demandes envoyées d\'un coup. Merci de patienter %d minute%s avant de réessayer, ou appelez-nous directement au 07 83 74 83 11.',
                    $minutes,
                    $minutes > 1 ? 's' : ''
                ));
                return $this->redirectToRoute('contact');
            }

            // ─── 3. Cloudflare Turnstile : vérification du captcha ───
            $token = $request->request->get('cf-turnstile-response');
            if (!$turnstile->verify((string) $token, $request->getClientIp())) {
                $this->addFlash('error', 'La vérification anti-spam a échoué. Veuillez recharger la page et réessayer.');
                return $this->redirectToRoute('contact');
            }

            // ─── 4. Tout est OK → envoi du message ───
            try {
                $contactService->sendContactMessage($contact);
                $this->addFlash('success', 'Votre message est bien parti — on vous répond sous 24 à 48 h.');
            } catch (\Throwable $e) {
                $logger->error('[Contact] Échec envoi : ' . $e->getMessage());
                $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi. Appelez-nous au 07 83 74 83 11 ou réessayez plus tard.');
            }

            return $this->redirectToRoute('contact');
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
