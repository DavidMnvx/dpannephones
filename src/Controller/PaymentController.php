<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Commande;
use App\Entity\CommandeItem;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/payment')]
class PaymentController extends AbstractController
{
    public function __construct(
        private string $stripeSecretKey,
        private string $stripePublicKey,
        private string $stripeWebhookSecret,
    ) {}

    /**
     * Crée une Stripe Checkout Session et redirige vers la page de paiement Stripe.
     */
    #[Route('/checkout', name: 'payment_checkout', methods: ['GET'])]
    public function checkout(EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cartId = $session->get('cart_id');
        $cart   = $cartId ? $em->getRepository(Cart::class)->find($cartId) : null;

        if (!$cart || $cart->getItems()->isEmpty()) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('cart_index');
        }

        $stripe    = new StripeClient($this->stripeSecretKey);
        $lineItems = [];

        foreach ($cart->getItems() as $item) {
            $article     = $item->getArticle();
            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'eur',
                    'product_data' => [
                        'name' => $article->getName(),
                    ],
                    'unit_amount' => (int) round($article->getPrice() * 100), // centimes
                ],
                'quantity' => $item->getQuantity(),
            ];
        }

        $checkoutSession = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items'           => $lineItems,
            'mode'                 => 'payment',
            'customer_email'       => $this->getUser()?->getEmail(),
            'metadata'             => ['cart_id' => $cart->getId()],
            'success_url'          => $this->generateUrl(
                'payment_success',
                ['session_id' => '{CHECKOUT_SESSION_ID}'],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'cancel_url' => $this->generateUrl(
                'payment_cancel',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ]);

        return $this->redirect($checkoutSession->url);
    }

    /**
     * Page de succès — Stripe redirige ici après un paiement réussi.
     * On vérifie le statut côté Stripe avant de créer la commande.
     */
    #[Route('/success', name: 'payment_success', methods: ['GET'])]
    public function success(Request $request, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $sessionId = $request->query->get('session_id');

        if (!$sessionId) {
            return $this->redirectToRoute('cart_index');
        }

        // Idempotence : commande déjà créée pour cette session ?
        $existingCommande = $em->getRepository(Commande::class)->findOneBy(['stripeSessionId' => $sessionId]);
        if ($existingCommande) {
            return $this->render('payment/success.html.twig', ['commande' => $existingCommande]);
        }

        // Vérifier le paiement directement auprès de Stripe
        $stripe = new StripeClient($this->stripeSecretKey);
        try {
            $stripeSession = $stripe->checkout->sessions->retrieve($sessionId);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Impossible de vérifier le paiement. Contactez-nous si vous avez été débité.');
            return $this->redirectToRoute('cart_index');
        }

        if ($stripeSession->payment_status !== 'paid') {
            $this->addFlash('warning', 'Le paiement est en attente de confirmation.');
            return $this->redirectToRoute('cart_index');
        }

        // Récupérer le panier depuis les metadata Stripe
        $cartId = $stripeSession->metadata->cart_id ?? null;
        $cart   = $cartId ? $em->getRepository(Cart::class)->find($cartId) : null;

        // Créer la commande
        $commande = new Commande();
        $commande->setStripeSessionId($sessionId);
        $commande->setStatut('payee');
        $commande->setUser($this->getUser());

        $total = 0;
        if ($cart) {
            foreach ($cart->getItems() as $cartItem) {
                $article = $cartItem->getArticle();
                $item    = new CommandeItem();
                $item->setArticle($article);
                $item->setArticleName($article->getName());
                $item->setPrix($article->getPrice());
                $item->setQuantite($cartItem->getQuantity());
                $commande->addItem($item);
                $total += $article->getPrice() * $cartItem->getQuantity();
            }

            $em->remove($cart);
            $session->remove('cart_id');
        }

        $commande->setTotal($total);
        $em->persist($commande);
        $em->flush();

        return $this->render('payment/success.html.twig', ['commande' => $commande]);
    }

    /**
     * Page d'annulation — Stripe redirige ici si l'utilisateur quitte la page de paiement.
     */
    #[Route('/cancel', name: 'payment_cancel', methods: ['GET'])]
    public function cancel(): Response
    {
        return $this->render('payment/cancel.html.twig');
    }

    /**
     * Webhook Stripe — appelé automatiquement par Stripe pour confirmer les paiements.
     * Utiliser en production pour une fiabilité maximale (même si la page success est inaccessible).
     */
    #[Route('/webhook', name: 'payment_webhook', methods: ['POST'])]
    public function webhook(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');
        $stripe    = new StripeClient($this->stripeSecretKey);

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
        } catch (SignatureVerificationException $e) {
            return new JsonResponse(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Webhook error'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $stripeSession = $event->data->object;

            if ($stripeSession->payment_status !== 'paid') {
                return new JsonResponse(['status' => 'ignored']);
            }

            // Idempotence
            $existing = $em->getRepository(Commande::class)->findOneBy(['stripeSessionId' => $stripeSession->id]);
            if ($existing) {
                return new JsonResponse(['status' => 'already_processed']);
            }

            $cartId = $stripeSession->metadata->cart_id ?? null;
            $cart   = $cartId ? $em->getRepository(Cart::class)->find($cartId) : null;

            $commande = new Commande();
            $commande->setStripeSessionId($stripeSession->id);
            $commande->setStatut('payee');

            $total = 0;
            if ($cart) {
                foreach ($cart->getItems() as $cartItem) {
                    $article = $cartItem->getArticle();
                    $item    = new CommandeItem();
                    $item->setArticle($article);
                    $item->setArticleName($article->getName());
                    $item->setPrix($article->getPrice());
                    $item->setQuantite($cartItem->getQuantity());
                    $commande->addItem($item);
                    $total += $article->getPrice() * $cartItem->getQuantity();
                }
                $em->remove($cart);
            }

            $commande->setTotal($total);
            $em->persist($commande);
            $em->flush();
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
