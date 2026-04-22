<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use App\Service\ContactNotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact')]
    public function index(
        Request $request,
        ContactNotificationService $contactService,
        LoggerInterface $logger
    ): Response {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $contactService->sendContactMessage($contact);
                $this->addFlash('success', '✅ Votre message a bien été envoyé — nous vous répondons sous 24-48h.');
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
