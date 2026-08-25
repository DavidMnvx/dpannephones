<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Commande;
use App\Entity\CommandeItem;
use App\Entity\PromoCode;
use App\Entity\User;
use App\Repository\PromoCodeRepository;
use App\Service\CommandeNotificationService;
use App\Service\ShippingOptionsResolver;
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
        private CommandeNotificationService $notif,
        private ShippingOptionsResolver $shippingResolver,
    ) {}

    /**
     * Crée une Stripe Checkout Session et redirige vers la page de paiement Stripe.
     */
    #[Route('/checkout', name: 'payment_checkout', methods: ['GET'])]
    public function checkout(
        EntityManagerInterface $em,
        SessionInterface $session,
        PromoCodeRepository $promoRepo,
        \Psr\Log\LoggerInterface $logger
    ): Response
    {
        $cartId = $session->get('cart_id');
        $cart   = $cartId ? $em->getRepository(Cart::class)->find($cartId) : null;

        if (!$cart || $cart->getItems()->isEmpty()) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('cart_index');
        }

        // Force IPv4 pour éviter les timeouts liés à la résolution IPv6 sur macOS
        $curlClient = new \Stripe\HttpClient\CurlClient([CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]);
        \Stripe\ApiRequestor::setHttpClient($curlClient);

        $stripe = new StripeClient($this->stripeSecretKey);

        $lineItems = [];

        foreach ($cart->getItems() as $item) {
            $article     = $item->getArticle();
            // Prix HT (les prix sont stockés TTC, TVA 20%)
            $priceHT = (int) round(($article->getPrice() / 1.20) * 100); // centimes HT

            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'eur',
                    'product_data' => [
                        'name'        => $item->getDisplayName(),
                        'description' => $article->getCategorie()
                            ? ucfirst(str_replace('_', ' ', $article->getCategorie()))
                            : null,
                    ],
                    'unit_amount'  => $priceHT,
                    'tax_behavior' => 'exclusive', // TVA en plus, affichée séparément
                ],
                'quantity'    => $item->getQuantity(),
                'tax_rates'   => ['txr_placeholder'], // remplacé ci-dessous
            ];
        }

        // Créer un taux de TVA à la volée si besoin (20 % FR)
        try {
            $taxRates = $stripe->taxRates->all(['limit' => 10, 'active' => true]);
            $tvaRate  = null;
            foreach ($taxRates->data as $rate) {
                if ($rate->percentage == 20.0 && $rate->inclusive === false) {
                    $tvaRate = $rate->id;
                    break;
                }
            }
            if (!$tvaRate) {
                $created = $stripe->taxRates->create([
                    'display_name' => 'TVA',
                    'description'  => 'TVA française 20%',
                    'jurisdiction' => 'FR',
                    'percentage'   => 20.0,
                    'inclusive'    => false,
                ]);
                $tvaRate = $created->id;
            }
        } catch (\Exception $e) {
            $tvaRate = null;
        }

        // Injecter le vrai tax_rate dans chaque ligne
        if ($tvaRate) {
            foreach ($lineItems as &$li) {
                $li['tax_rates'] = [$tvaRate];
            }
            unset($li);
        } else {
            // Fallback : prix TTC sans détail TVA
            foreach ($lineItems as &$li) {
                unset($li['tax_rates']);
                $li['price_data']['unit_amount']  = (int) round($li['price_data']['unit_amount'] * 1.20);
                $li['price_data']['tax_behavior'] = 'inclusive';
            }
            unset($li);
        }

        // ── Récupérer et valider le mode de livraison choisi ──
        $cartCategories    = ShippingOptionsResolver::extractCategories($cart->getItems());
        $availableShipping = $this->shippingResolver->getAvailableOptions($cartCategories);

        // Tunnel de commande : le paiement est verrouillé tant que l'étape livraison
        // n'est pas complète — pas de mode par défaut silencieux
        $shippingMode = $session->get('shipping_mode');
        if (!$shippingMode || !isset($availableShipping[$shippingMode])) {
            $this->addFlash('warning', 'Choisissez votre mode de livraison avant de passer au paiement.');
            return $this->redirectToRoute('checkout_shipping');
        }

        $relayPoint = $session->get('relay_point');
        if ($shippingMode === 'mondial_relay' && !$relayPoint) {
            $this->addFlash('warning', 'Sélectionnez votre point relais avant de passer au paiement.');
            return $this->redirectToRoute('checkout_shipping');
        }

        $shippingOption = $availableShipping[$shippingMode];
        $shippingCost   = (float) $shippingOption['price'];

        // Ajouter une ligne Stripe pour les frais de livraison (si > 0)
        if ($shippingCost > 0) {
            // Prix HT des frais de livraison (TVA 20 %)
            $shippingPriceHT = (int) round(($shippingCost / 1.20) * 100); // centimes HT

            $shippingLine = [
                'price_data' => [
                    'currency'     => 'eur',
                    'product_data' => [
                        'name'        => 'Livraison — ' . $shippingOption['label'],
                        'description' => $shippingOption['delay'],
                    ],
                    'unit_amount'  => $shippingPriceHT,
                ],
                'quantity' => 1,
            ];

            if ($tvaRate) {
                $shippingLine['price_data']['tax_behavior'] = 'exclusive';
                $shippingLine['tax_rates'] = [$tvaRate];
            } else {
                // Fallback TTC inclusive
                $shippingLine['price_data']['unit_amount'] = (int) round($shippingCost * 100);
                $shippingLine['price_data']['tax_behavior'] = 'inclusive';
            }

            $lineItems[] = $shippingLine;
        }

        // ── Code promo : revalider + créer un coupon Stripe à la volée ──
        $promoCodeString = $session->get('promo_code');
        $promoCode       = null;
        $discountAmount  = 0.0;
        $stripeCoupon    = null;

        if ($promoCodeString) {
            // Calcul du sous-total (TTC articles, hors livraison) pour validation
            $subtotal = 0;
            foreach ($cart->getItems() as $cartItem) {
                $subtotal += $cartItem->getArticle()->getPrice() * $cartItem->getQuantity();
            }

            [$validPromo, $err] = $promoRepo->findValidByCode($promoCodeString, $subtotal);

            if ($validPromo) {
                $promoCode      = $validPromo;
                $discountAmount = $validPromo->computeDiscount($subtotal);

                // Créer un coupon Stripe "one-shot" — se nettoie automatiquement après usage
                try {
                    if ($promoCode->getType() === PromoCode::TYPE_FIXED) {
                        $stripeCoupon = $stripe->coupons->create([
                            'amount_off' => (int) round($discountAmount * 100), // centimes
                            'currency'   => 'eur',
                            'duration'   => 'once',
                            'name'       => 'Code promo ' . $promoCode->getCode(),
                        ]);
                    } else {
                        $stripeCoupon = $stripe->coupons->create([
                            'percent_off' => (float) $promoCode->getValue(),
                            'duration'    => 'once',
                            'name'        => 'Code promo ' . $promoCode->getCode(),
                        ]);
                    }
                } catch (\Exception $e) {
                    // Si la création du coupon échoue, on annule la promo côté commande
                    $promoCode      = null;
                    $discountAmount = 0.0;
                    $stripeCoupon   = null;
                }
            } else {
                // Promo devenue invalide (expiration, limite atteinte…) : on la retire
                $session->remove('promo_code');
            }
        }

        // On stocke l'ID utilisateur + mode de livraison + promo dans les métadonnées Stripe
        $userId = $this->getUser()?->getId();

        $checkoutParams = [
            // Pas de 'payment_method_types' : Checkout applique alors automatiquement les
            // moyens de paiement activés dans le dashboard Stripe (Réglages > Moyens de
            // paiement) — carte, PayPal, Alma (3-4x), Apple/Google Pay… Activer/désactiver
            // là-bas suffit, aucun changement de code. Stripe n'affiche que ceux qui sont
            // éligibles au montant et à la devise du panier.
            'line_items'           => $lineItems,
            'mode'                 => 'payment',
            'customer_email'       => $this->getUser()?->getEmail(),
            'metadata'             => [
                'cart_id'         => $cart->getId(),
                'user_id'         => $userId,
                'shipping_mode'   => $shippingMode,
                'relay_point'     => $relayPoint ? json_encode($relayPoint, JSON_UNESCAPED_UNICODE) : null,
                'shipping_cost'   => number_format($shippingCost, 2, '.', ''),
                'promo_code_id'   => $promoCode?->getId(),
                'promo_code'      => $promoCode?->getCode(),
                'discount_amount' => number_format($discountAmount, 2, '.', ''),
            ],
            // On construit l'URL manuellement pour éviter que Symfony encode {} en %7B%7D
            'success_url' => $this->generateUrl('payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
                . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $this->generateUrl('payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        // Appliquer le coupon Stripe si présent (permet l'affichage natif du discount)
        if ($stripeCoupon) {
            $checkoutParams['discounts'] = [['coupon' => $stripeCoupon->id]];
        }

        try {
            $checkoutSession = $stripe->checkout->sessions->create($checkoutParams);
        } catch (\Exception $e) {
            // Clé invalide, panne réseau, paramètre refusé… : erreur maîtrisée au lieu d'une page 500
            $logger->error('Création de la session Stripe Checkout impossible : ' . $e->getMessage(), [
                'exception' => $e,
                'cart_id'   => $cart->getId(),
            ]);
            $this->addFlash('danger', "Le paiement en ligne est momentanément indisponible. Réessayez dans quelques instants ou contactez-nous au 07 83 74 83 11.");

            return $this->redirectToRoute('cart_index');
        }

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
            // Paiement différé (Alma, PayPal en cours…) : Stripe confirmera par webhook
            // (async_payment_succeeded) et la commande sera créée à ce moment-là.
            $this->addFlash('info', 'Votre paiement est en cours de confirmation par votre prestataire (PayPal, Alma…). '
                . 'Vous recevrez votre confirmation de commande par e-mail dès validation — généralement en quelques minutes. '
                . 'Ne relancez pas le paiement.');
            return $this->redirectToRoute('boutique_index');
        }

        // Récupérer le panier depuis les métadonnées Stripe
        $cartId = $stripeSession->metadata->cart_id ?? null;
        $cart   = $cartId ? $em->getRepository(Cart::class)->find($cartId) : null;

        // Récupérer le mode de livraison depuis les métadonnées
        $shippingMode = $stripeSession->metadata->shipping_mode ?? null;
        $shippingCost = isset($stripeSession->metadata->shipping_cost)
            ? (float) $stripeSession->metadata->shipping_cost
            : 0.0;

        // Récupérer les infos de code promo depuis les métadonnées
        $promoCodeId      = $stripeSession->metadata->promo_code_id ?? null;
        $promoCodeString  = $stripeSession->metadata->promo_code ?? null;
        $discountAmount   = isset($stripeSession->metadata->discount_amount)
            ? (float) $stripeSession->metadata->discount_amount
            : 0.0;

        // Créer la commande
        $commande = new Commande();
        $commande->setStripeSessionId($sessionId);
        $commande->setStatut('payee');
        $commande->setUser($this->getUser());

        if ($shippingMode) {
            $commande->setShippingMode($shippingMode);
            if (!empty($stripeSession->metadata->relay_point)) {
                $decodedRelay = json_decode((string) $stripeSession->metadata->relay_point, true);
                if (is_array($decodedRelay)) {
                    $commande->setRelayPoint($decodedRelay);
                }
            }
            $commande->setShippingCost($shippingCost);
            $commande->setTransporteur($this->shippingResolver->getCarrierName($shippingMode));
        }

        // Appliquer la promo sur la commande + incrémenter l'usage
        if ($promoCodeId) {
            $promoEntity = $em->getRepository(PromoCode::class)->find((int) $promoCodeId);
            if ($promoEntity) {
                $commande->setPromoCode($promoEntity);
                $commande->setPromoCodeUsed($promoCodeString);
                $commande->setDiscountAmount($discountAmount);
                $promoEntity->incrementUsage();
            } elseif ($promoCodeString) {
                // Promo supprimée entre-temps : on garde la trace du code utilisé
                $commande->setPromoCodeUsed($promoCodeString);
                $commande->setDiscountAmount($discountAmount);
            }
        }

        $total = 0;
        if ($cart) {
            foreach ($cart->getItems() as $cartItem) {
                $article = $cartItem->getArticle();
                $item    = new CommandeItem();
                $item->setArticle($article);
                $item->setArticleName($cartItem->getDisplayName());
                $item->setPrix($article->getPrice());
                $item->setQuantite($cartItem->getQuantity());
                $commande->addItem($item);
                $total += $article->getPrice() * $cartItem->getQuantity();
            }

            $em->remove($cart);
            $session->remove('cart_id');
            $session->remove('shipping_mode');
            $session->remove('promo_code');
        }

        // Total = articles - remise + frais de livraison
        $commande->setTotal(max(0, $total - $discountAmount + $shippingCost));
        $em->persist($commande);
        $em->flush();

        // Email de confirmation au client
        try {
            $this->notif->notifyStatutChange($commande);
        } catch (\Exception $e) {
            // L'email échoue silencieusement — la commande est quand même sauvegardée
        }

        // Notification à l'équipe D'Panne Phones (nouvelle commande à préparer)
        try {
            $this->notif->notifyAdminNewCommande($commande);
        } catch (\Exception $e) {
            // L'email admin échoue silencieusement — ne doit pas bloquer la commande
        }

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
     * Filet de sécurité : crée la commande si la page success n'a pas pu être atteinte.
     */
    #[Route('/webhook', name: 'payment_webhook', methods: ['POST'])]
    public function webhook(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
        } catch (SignatureVerificationException $e) {
            return new JsonResponse(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Webhook error'], 400);
        }

        // checkout.session.completed : paiement immédiat (carte, PayPal instantané…).
        // checkout.session.async_payment_succeeded : moyens différés (Alma 3-4x, certains
        // PayPal, virements) — la session est d'abord "completed" avec payment_status=unpaid,
        // puis ce second événement confirme l'encaissement : c'est là qu'on crée la commande.
        if (in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $stripeSession = $event->data->object;

            if ($stripeSession->payment_status !== 'paid') {
                return new JsonResponse(['status' => 'ignored']);
            }

            // Idempotence — la page success a déjà créé la commande
            $existing = $em->getRepository(Commande::class)->findOneBy(['stripeSessionId' => $stripeSession->id]);
            if ($existing) {
                return new JsonResponse(['status' => 'already_processed']);
            }

            $cartId = $stripeSession->metadata->cart_id ?? null;
            $cart   = $cartId ? $em->getRepository(Cart::class)->find($cartId) : null;

            // Récupérer l'utilisateur depuis les métadonnées
            $userId = $stripeSession->metadata->user_id ?? null;
            $user   = $userId ? $em->getRepository(User::class)->find((int) $userId) : null;

            // Récupérer le mode de livraison
            $shippingMode = $stripeSession->metadata->shipping_mode ?? null;
            $shippingCost = isset($stripeSession->metadata->shipping_cost)
                ? (float) $stripeSession->metadata->shipping_cost
                : 0.0;

            // Récupérer les infos de code promo
            $promoCodeId     = $stripeSession->metadata->promo_code_id ?? null;
            $promoCodeString = $stripeSession->metadata->promo_code ?? null;
            $discountAmount  = isset($stripeSession->metadata->discount_amount)
                ? (float) $stripeSession->metadata->discount_amount
                : 0.0;

            $commande = new Commande();
            $commande->setStripeSessionId($stripeSession->id);
            $commande->setStatut('payee');
            $commande->setUser($user);

            if ($shippingMode) {
                $commande->setShippingMode($shippingMode);
            if (!empty($stripeSession->metadata->relay_point)) {
                $decodedRelay = json_decode((string) $stripeSession->metadata->relay_point, true);
                if (is_array($decodedRelay)) {
                    $commande->setRelayPoint($decodedRelay);
                }
            }
                $commande->setShippingCost($shippingCost);
                $commande->setTransporteur($this->shippingResolver->getCarrierName($shippingMode));
            }

            // Appliquer la promo sur la commande + incrémenter l'usage
            if ($promoCodeId) {
                $promoEntity = $em->getRepository(PromoCode::class)->find((int) $promoCodeId);
                if ($promoEntity) {
                    $commande->setPromoCode($promoEntity);
                    $commande->setPromoCodeUsed($promoCodeString);
                    $commande->setDiscountAmount($discountAmount);
                    $promoEntity->incrementUsage();
                } elseif ($promoCodeString) {
                    $commande->setPromoCodeUsed($promoCodeString);
                    $commande->setDiscountAmount($discountAmount);
                }
            }

            $total = 0;
            if ($cart) {
                foreach ($cart->getItems() as $cartItem) {
                    $article = $cartItem->getArticle();
                    $item    = new CommandeItem();
                    $item->setArticle($article);
                    $item->setArticleName($cartItem->getDisplayName());
                    $item->setPrix($article->getPrice());
                    $item->setQuantite($cartItem->getQuantity());
                    $commande->addItem($item);
                    $total += $article->getPrice() * $cartItem->getQuantity();
                }
                $em->remove($cart);
            }

            $commande->setTotal(max(0, $total - $discountAmount + $shippingCost));
            $em->persist($commande);
            $em->flush();

            // Email de confirmation au client
            try {
                $this->notif->notifyStatutChange($commande);
            } catch (\Exception $e) {
                // Silencieux
            }

            // Notification à l'équipe D'Panne Phones
            try {
                $this->notif->notifyAdminNewCommande($commande);
            } catch (\Exception $e) {
                // Silencieux
            }
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
