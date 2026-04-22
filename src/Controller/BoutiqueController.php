<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Commande;
use App\Entity\CommandeItem;
use App\Repository\PromoCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/boutique')]
class BoutiqueController extends AbstractController
{
    const CATEGORIES = [
        'pc_gamer'       => 'PC Gamer',
        'pc_bureautique' => 'PC Bureautique',
        'pc_portable'    => 'PC Portable',
        'accessoires'    => 'Accessoires',
        'film_hydrogel'  => 'Film Hydrogel',
        'telephone'      => 'Téléphones',
    ];

    #[Route('/', name: 'boutique_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em, SessionInterface $session, Request $request): Response
    {
        $categorie = $request->query->get('categorie');
        $brand     = $request->query->get('brand');

        if ($categorie && array_key_exists($categorie, self::CATEGORIES)) {
            $articles = $em->getRepository(Article::class)->findBy(['categorie' => $categorie]);
        } else {
            $categorie = null;
            $articles  = $em->getRepository(Article::class)->findAll();
        }

        // Filtre par marque (téléphones uniquement)
        $availableBrands = [];
        if ($categorie === 'telephone') {
            foreach ($articles as $a) {
                $b = $a->getSpecs()['brand'] ?? null;
                if ($b && !in_array($b, $availableBrands)) {
                    $availableBrands[] = $b;
                }
            }
            sort($availableBrands);

            if ($brand) {
                $articles = array_filter($articles, fn($a) => ($a->getSpecs()['brand'] ?? null) === $brand);
            }
        }

        $cart = $this->getCurrentCart($em, $session);

        // Calcul total mini-panier
        $cartItems = $cart ? $cart->getItems() : [];
        $cartTotal = 0;
        foreach ($cartItems as $item) {
            $cartTotal += $item->getArticle()->getPrice() * $item->getQuantity();
        }

        return $this->render('boutique/index.html.twig', [
            'articles'         => $articles,
            'cart'             => $cartItems,
            'cartTotal'        => $cartTotal,
            'currentCategorie' => $categorie,
            'categories'       => self::CATEGORIES,
            'availableBrands'  => $availableBrands,
            'currentBrand'     => $brand,
        ]);
    }

    #[Route('/article/{id}', name: 'boutique_show', methods: ['GET'])]
    public function show(
        Article $article,
        EntityManagerInterface $em,
        SessionInterface $session,
        \App\Repository\ReviewRepository $reviewRepo
    ): Response
    {
        $cart = $this->getCurrentCart($em, $session);

        // Avis approuvés pour cet article
        $articleReviews      = $reviewRepo->findApprovedForArticle($article);
        $articleAvgRating    = $reviewRepo->getAverageRating($article);
        $articleRatingDist   = $reviewRepo->getRatingDistribution($article);

        return $this->render('boutique/show.html.twig', [
            'article'           => $article,
            'cart'              => $cart ? $cart->getItems() : [],
            'categories'        => self::CATEGORIES,
            'articleReviews'    => $articleReviews,
            'articleAvgRating'  => $articleAvgRating,
            'articleRatingDist' => $articleRatingDist,
        ]);
    }

    #[Route('/cart/add/{id}', name: 'cart_add', methods: ['GET'])]
    public function add(Article $article, Request $request, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cart = $this->getOrCreateCart($em, $session);
        $cartItem = $this->findOrCreateCartItem($cart, $article, $em);

        $cartItem->setQuantity($cartItem->getQuantity() + 1);

        $model = $request->query->get('model');
        if ($model && $article->getCategorie() === 'film_hydrogel') {
            $cartItem->setOptions(['model' => $model]);
        }

        $em->persist($cartItem);
        $em->flush();

        return $this->redirectToRoute('boutique_index');
    }

    #[Route('/cart/confirm', name: 'cart_confirm', methods: ['GET'])]
    public function confirm(EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cart = $this->getCurrentCart($em, $session);

        if (!$cart || $cart->getItems()->isEmpty()) {
            return $this->redirectToRoute('cart_index');
        }

        $commande = new Commande();
        $total = 0;

        foreach ($cart->getItems() as $cartItem) {
            $item = new CommandeItem();
            $item->setArticle($cartItem->getArticle());
            $item->setArticleName($cartItem->getArticle()->getName());
            $item->setPrix($cartItem->getArticle()->getPrice());
            $item->setQuantite($cartItem->getQuantity());
            $commande->addItem($item);
            $total += $cartItem->getArticle()->getPrice() * $cartItem->getQuantity();
        }

        $commande->setTotal($total);
        $em->persist($commande);

        // Vider le panier
        $em->remove($cart);
        $em->flush();
        $session->remove('cart_id');

        $this->addFlash('success', 'Votre commande a bien été enregistrée ! Nous vous contacterons rapidement.');

        return $this->redirectToRoute('boutique_index');
    }

    #[Route('/cart/increment/{id}', name: 'cart_increment', methods: ['GET'])]
    public function increment(int $id, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cart = $this->getCurrentCart($em, $session);
        if ($cart) {
            $article  = $em->getRepository(Article::class)->find($id);
            $cartItem = $article ? $em->getRepository(CartItem::class)->findOneBy(['cart' => $cart, 'article' => $article]) : null;
            if ($cartItem) {
                $cartItem->setQuantity($cartItem->getQuantity() + 1);
                $em->flush();
            }
        }
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/decrement/{id}', name: 'cart_decrement', methods: ['GET'])]
    public function decrement(int $id, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cart = $this->getCurrentCart($em, $session);
        if ($cart) {
            $article  = $em->getRepository(Article::class)->find($id);
            $cartItem = $article ? $em->getRepository(CartItem::class)->findOneBy(['cart' => $cart, 'article' => $article]) : null;
            if ($cartItem) {
                if ($cartItem->getQuantity() <= 1) {
                    $em->remove($cartItem);
                } else {
                    $cartItem->setQuantity($cartItem->getQuantity() - 1);
                }
                $em->flush();
            }
        }
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/remove/{id}', name: 'cart_remove', methods: ['GET'])]
    public function remove(int $id, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cart = $this->getCurrentCart($em, $session);

        if ($cart) {
            $article = $em->getRepository(Article::class)->find($id);
            if ($article) {
                $cartItem = $em->getRepository(CartItem::class)->findOneBy([
                    'cart'    => $cart,
                    'article' => $article,
                ]);
                if ($cartItem) {
                    $em->remove($cartItem);
                    $em->flush();
                }
            }
        }

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart', name: 'cart_index', methods: ['GET'])]
    public function cartIndex(
        EntityManagerInterface $em,
        SessionInterface $session,
        \App\Service\ShippingOptionsResolver $shipping,
        PromoCodeRepository $promoRepo
    ): Response
    {
        $cart = $this->getCurrentCart($em, $session);
        $items = $cart ? $cart->getItems() : [];

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item->getArticle()->getPrice() * $item->getQuantity();
        }

        // Résolution des options de livraison disponibles
        $cartCategories    = \App\Service\ShippingOptionsResolver::extractCategories($items);
        $availableShipping = $shipping->getAvailableOptions($cartCategories);
        $hasBulky          = $shipping->hasBulkyItem($cartCategories);
        $bulkyLabel        = $shipping->getBulkyLabel($cartCategories);

        // Mode sélectionné (session) — vérifié contre la liste dispo
        $selectedMode = $session->get('shipping_mode');
        if (!$selectedMode || !isset($availableShipping[$selectedMode])) {
            $selectedMode = $shipping->getDefaultOption($cartCategories);
            $session->set('shipping_mode', $selectedMode);
        }

        $shippingCost = $shipping->getCost($selectedMode);

        // ─── Code promo : revalider systématiquement à chaque affichage ───
        // (le panier peut avoir évolué, le code peut avoir expiré entre-temps)
        $promoCode       = null;
        $promoDiscount   = 0.0;
        $promoError      = null;
        $promoCodeString = $session->get('promo_code');

        if ($promoCodeString) {
            [$promo, $err] = $promoRepo->findValidByCode($promoCodeString, $subtotal);
            if ($promo) {
                $promoCode     = $promo;
                $promoDiscount = $promo->computeDiscount($subtotal);
            } else {
                // Le code stocké en session n'est plus valide → on le retire silencieusement
                $session->remove('promo_code');
                $promoError = $err;
            }
        }

        $total = $subtotal - $promoDiscount + $shippingCost;
        if ($total < 0) {
            $total = 0;
        }

        return $this->render('cart/index.html.twig', [
            'cart'              => $items,
            'subtotal'          => $subtotal,
            'shippingCost'      => $shippingCost,
            'total'             => $total,
            'availableShipping' => $availableShipping,
            'selectedMode'      => $selectedMode,
            'hasBulky'          => $hasBulky,
            'bulkyLabel'        => $bulkyLabel,
            'promoCode'         => $promoCode,
            'promoDiscount'     => $promoDiscount,
            'promoError'        => $promoError,
        ]);
    }

    #[Route('/cart/apply-promo', name: 'cart_apply_promo', methods: ['POST'])]
    public function applyPromo(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $em,
        PromoCodeRepository $promoRepo
    ): Response {
        $code = (string) $request->request->get('promo_code', '');
        $code = trim($code);

        if ($code === '') {
            $this->addFlash('error', 'Saisissez un code promo.');
            return $this->redirectToRoute('cart_index');
        }

        // Calculer le sous-total actuel pour valider le minimum panier
        $cart     = $this->getCurrentCart($em, $session);
        $items    = $cart ? $cart->getItems() : [];
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item->getArticle()->getPrice() * $item->getQuantity();
        }

        [$promo, $err] = $promoRepo->findValidByCode($code, $subtotal);

        if (!$promo) {
            $this->addFlash('error', $err ?? 'Code promo invalide.');
            return $this->redirectToRoute('cart_index');
        }

        $session->set('promo_code', $promo->getCode());
        $this->addFlash('success', sprintf(
            'Code "%s" appliqué : remise de %s',
            $promo->getCode(),
            $promo->getDisplayValue()
        ));

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/remove-promo', name: 'cart_remove_promo', methods: ['POST'])]
    public function removePromo(SessionInterface $session): Response
    {
        $session->remove('promo_code');
        $this->addFlash('info', 'Code promo retiré du panier.');
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/shipping', name: 'cart_set_shipping', methods: ['POST'])]
    public function setShipping(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $em,
        \App\Service\ShippingOptionsResolver $shipping
    ): Response
    {
        $mode = $request->request->get('shipping_mode', '');

        // Vérifier que le mode existe ET qu'il est compatible avec le panier actuel
        $cart  = $this->getCurrentCart($em, $session);
        $items = $cart ? $cart->getItems() : [];
        $cartCategories    = \App\Service\ShippingOptionsResolver::extractCategories($items);
        $availableShipping = $shipping->getAvailableOptions($cartCategories);

        if (isset($availableShipping[$mode])) {
            $session->set('shipping_mode', $mode);
        }

        return $this->redirectToRoute('cart_index');
    }

    private function getOrCreateCart(EntityManagerInterface $em, SessionInterface $session): Cart
    {
        $cart = $this->getCurrentCart($em, $session);

        if (!$cart) {
            $cart = new Cart();
            $em->persist($cart);
            $em->flush();
            $session->set('cart_id', $cart->getId());
        }

        return $cart;
    }

    private function getCurrentCart(EntityManagerInterface $em, SessionInterface $session): ?Cart
    {
        $cartId = $session->get('cart_id');

        if (!$cartId) {
            return null;
        }

        return $em->getRepository(Cart::class)->find($cartId);
    }

    private function findOrCreateCartItem(Cart $cart, Article $article, EntityManagerInterface $em): CartItem
    {
        $cartItem = $em->getRepository(CartItem::class)->findOneBy([
            'cart'    => $cart,
            'article' => $article,
        ]);

        if (!$cartItem) {
            $cartItem = new CartItem();
            $cartItem->setCart($cart);
            $cartItem->setArticle($article);
            $cartItem->setQuantity(0);
        }

        return $cartItem;
    }
}
