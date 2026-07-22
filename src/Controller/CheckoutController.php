<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Repository\PromoCodeRepository;
use App\Service\AppSettingService;
use App\Service\ShippingOptionsResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Tunnel de commande — étape Livraison.
 *
 * Parcours : Panier (validation) → Livraison (mode + point relais) → Paiement Stripe.
 * Le passage au paiement est verrouillé tant que l'étape livraison n'est pas complète :
 *  - un mode de livraison compatible avec le panier doit être choisi
 *  - si le mode est "mondial_relay", un point relais doit être sélectionné
 */
#[Route('/boutique/commande')]
class CheckoutController extends AbstractController
{
    #[Route('/livraison', name: 'checkout_shipping', methods: ['GET'])]
    public function shipping(
        EntityManagerInterface $em,
        SessionInterface $session,
        ShippingOptionsResolver $shippingResolver,
        AppSettingService $settings,
        PromoCodeRepository $promoRepo
    ): Response {
        $cartId = $session->get('cart_id');
        $cart   = $cartId ? $em->getRepository(Cart::class)->find($cartId) : null;

        if (!$cart || $cart->getItems()->isEmpty()) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('cart_index');
        }

        $items             = $cart->getItems();
        $cartCategories    = ShippingOptionsResolver::extractCategories($items);
        $availableShipping = $shippingResolver->getAvailableOptions($cartCategories);

        // Mode sélectionné : uniquement s'il a été choisi explicitement ET reste compatible
        $selectedMode = $session->get('shipping_mode');
        if ($selectedMode && !isset($availableShipping[$selectedMode])) {
            $selectedMode = null;
            $session->remove('shipping_mode');
        }

        $relayPoint = $session->get('relay_point');

        // Totaux (avec promo éventuelle, même logique que le panier)
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item->getArticle()->getPrice() * $item->getQuantity();
        }
        $discount = 0.0;
        if ($promoCodeString = $session->get('promo_code')) {
            [$validPromo] = $promoRepo->findValidByCode($promoCodeString, $subtotal);
            if ($validPromo) {
                $discount = $validPromo->computeDiscount($subtotal);
            }
        }
        $shippingCost = ($selectedMode && isset($availableShipping[$selectedMode]))
            ? (float) $availableShipping[$selectedMode]['price']
            : null;

        return $this->render('checkout/shipping.html.twig', [
            'cart'              => $items,
            'subtotal'          => $subtotal,
            'discount'          => $discount,
            'shippingCost'      => $shippingCost,
            'availableShipping' => $availableShipping,
            'selectedMode'      => $selectedMode,
            'relayPoint'        => $relayPoint,
            'mondialRelayBrand' => $settings->getString('mondial_relay_brand', 'BDTEST'),
        ]);
    }

    /**
     * Enregistre le point relais choisi dans le widget Mondial Relay (appel AJAX).
     */
    #[Route('/relais', name: 'checkout_set_relay', methods: ['POST'])]
    public function setRelay(Request $request, SessionInterface $session): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || empty($data['id']) || empty($data['name'])) {
            return new JsonResponse(['ok' => false], 400);
        }

        $session->set('relay_point', [
            'id'      => mb_substr((string) $data['id'], 0, 30),
            'name'    => mb_substr((string) $data['name'], 0, 120),
            'address' => mb_substr((string) ($data['address'] ?? ''), 0, 200),
            'zip'     => mb_substr((string) ($data['zip'] ?? ''), 0, 10),
            'city'    => mb_substr((string) ($data['city'] ?? ''), 0, 80),
        ]);

        return new JsonResponse(['ok' => true]);
    }
}
