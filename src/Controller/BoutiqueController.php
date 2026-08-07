<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Commande;
use App\Entity\CommandeItem;
use App\Repository\CategoryRepository;
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
    /** @deprecated Les catégories sont désormais en base (table category, admin /admin/categories). */
    const CATEGORIES = [
        'pc_gamer'             => 'PC Gamer',
        'pc_gamer_occasion'    => "PC Gamer d'occasion",
        'pc_bureautique'       => 'PC Bureautique',
        'pc_portable'          => 'PC Portable',
        'pc_portable_occasion' => "PC Portable d'occasion",
        'accessoires'    => 'Accessoires',
        'coque'          => 'Coques',
        'film_hydrogel'  => 'Film Hydrogel',
        'telephone'      => 'Téléphones',
    ];

    #[Route('/', name: 'boutique_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em, SessionInterface $session, Request $request, CategoryRepository $categoryRepo): Response
    {
        $categorie  = $request->query->get('categorie');
        $brand      = $request->query->get('brand');
        $categories = $categoryRepo->getSlugLabelMap();

        if ($categorie === 'occasions') {
            // Rayon transversal : tout le matériel d'occasion, tous rayons confondus
            $articles = $em->getRepository(Article::class)->createQueryBuilder('a')
                ->where('a.isNew IS NULL OR a.isNew = :faux')->setParameter('faux', false)
                ->orderBy('a.id', 'DESC')
                ->getQuery()->getResult();
        } elseif ($categorie && array_key_exists($categorie, $categories)) {
            $articles = $em->getRepository(Article::class)->findBy(['categorie' => $categorie], ['id' => 'DESC']);
        } else {
            $categorie = null;
            $articles  = $em->getRepository(Article::class)->findBy([], ['id' => 'DESC']);

            // Ordre stable : regroupés par catégorie (ordre de CATEGORIES), les plus récents d'abord
            $rank = array_flip(array_keys($categories));
            usort($articles, function (Article $a, Article $b) use ($rank) {
                $ra = $rank[$a->getCategorie()] ?? PHP_INT_MAX;
                $rb = $rank[$b->getCategorie()] ?? PHP_INT_MAX;
                return $ra === $rb ? $b->getId() <=> $a->getId() : $ra <=> $rb;
            });
        }

        // Compteurs par catégorie pour la sidebar
        $categoryCounts = ['__all__' => 0];
        foreach ($em->getRepository(Article::class)->createQueryBuilder('a')
                     ->select('a.categorie AS cat, COUNT(a.id) AS nb')
                     ->groupBy('a.categorie')->getQuery()->getArrayResult() as $row) {
            $categoryCounts[$row['cat'] ?? ''] = (int) $row['nb'];
            $categoryCounts['__all__'] += (int) $row['nb'];
        }
        $categoryCounts['occasions'] = (int) $em->getRepository(Article::class)->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.isNew IS NULL OR a.isNew = :faux')->setParameter('faux', false)
            ->getQuery()->getSingleScalarResult();

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

        // Articles "à la une" pour la bannière commerciale (accueil uniquement)
        $featuredArticles = $categorie === null
            ? $em->getRepository(Article::class)->findBy(['isFeatured' => true], ['id' => 'DESC'], 6)
            : [];

        // Section "Nouveautés" : les derniers articles ajoutés (accueil uniquement)
        $latestArticles = $categorie === null
            ? $em->getRepository(Article::class)->findBy([], ['id' => 'DESC'], 8)
            : [];

        // Section "Occasions" de l'accueil (mêmes articles que le rayon, limités)
        $occasionArticles = $categorie === null
            ? $em->getRepository(Article::class)->createQueryBuilder('a')
                ->where('a.isNew IS NULL OR a.isNew = :faux')->setParameter('faux', false)
                ->orderBy('a.id', 'DESC')->setMaxResults(8)
                ->getQuery()->getResult()
            : [];

        // Libellé de la catégorie affichée ("occasions" est un rayon virtuel, hors table)
        $currentCategorieLabel = $categorie === 'occasions'
            ? 'Occasions'
            : ($categories[$categorie] ?? null);

        return $this->render('boutique/index.html.twig', [
            'featuredArticles' => $featuredArticles,
            'latestArticles'   => $latestArticles,
            'occasionArticles' => $occasionArticles,
            'currentCategorieLabel' => $currentCategorieLabel,
            'articles'         => $articles,
            'cart'             => $cartItems,
            'cartTotal'        => $cartTotal,
            'currentCategorie' => $categorie,
            'categories'       => $categories,
            'categoryIcons'    => $categoryRepo->getSlugIconMap(),
            'categoryTemplates' => $categoryRepo->getSlugSpecsTemplateMap(),
            'categoryCounts'   => $categoryCounts,
            'availableBrands'  => $availableBrands,
            'currentBrand'     => $brand,
        ]);
    }

    #[Route('/article/{id}', name: 'boutique_show', methods: ['GET'])]
    public function show(
        Article $article,
        EntityManagerInterface $em,
        SessionInterface $session,
        \App\Repository\ReviewRepository $reviewRepo,
        CategoryRepository $categoryRepo
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
            'categories'        => $categoryRepo->getSlugLabelMap(),
            'articleReviews'    => $articleReviews,
            'articleAvgRating'  => $articleAvgRating,
            'articleRatingDist' => $articleRatingDist,
        ]);
    }

    #[Route('/cart/add/{id}', name: 'cart_add', methods: ['GET'])]
    public function add(Article $article, Request $request, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cart = $this->getOrCreateCart($em, $session);

        // Les options (couleur, modèle) font partie de l'identité de la ligne :
        // un même article en blanc et en noir = deux lignes distinctes du panier
        $options = [];
        $model = $request->query->get('model');
        $catTemplate = $em->getRepository(\App\Entity\Category::class)
            ->findOneBy(['slug' => $article->getCategorie()])?->getSpecsTemplate() ?? $article->getCategorie();
        if ($model && in_array($catTemplate, ['film_hydrogel', 'coque'], true)) {
            $options['model'] = $model;
        }
        $color = $request->query->get('color');
        if ($color) {
            $options['color'] = $color;
        }

        $cartItem = $this->findOrCreateCartItem($cart, $article, $options, $em);
        $cartItem->setQuantity($cartItem->getQuantity() + 1);

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
            $item->setArticleName($cartItem->getDisplayName());
            $item->setPrix($cartItem->getArticle()->getPrice());
            $item->setQuantite($cartItem->getQuantity());
            $commande->addItem($item);
            $total += $cartItem->getArticle()->getPrice() * $cartItem->getQuantity();
        }

        $commande->setTotal($total);
        if ($session->get('shipping_mode') === 'mondial_relay' && is_array($session->get('relay_point'))) {
            $commande->setRelayPoint($session->get('relay_point'));
        }
        $em->persist($commande);

        // Vider le panier
        $em->remove($cart);
        $em->flush();
        $session->remove('cart_id');

        $this->addFlash('success', 'Votre commande a bien été enregistrée ! Nous vous contacterons rapidement.');

        return $this->redirectToRoute('boutique_index');
    }

    // {id} désigne désormais la LIGNE de panier (CartItem), pas l'article —
    // indispensable pour distinguer deux couleurs d'un même article
    #[Route('/cart/increment/{id}', name: 'cart_increment', methods: ['GET'])]
    public function increment(int $id, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cartItem = $this->findOwnCartItem($id, $em, $session);
        if ($cartItem) {
            $cartItem->setQuantity($cartItem->getQuantity() + 1);
            $em->flush();
        }
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/decrement/{id}', name: 'cart_decrement', methods: ['GET'])]
    public function decrement(int $id, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cartItem = $this->findOwnCartItem($id, $em, $session);
        if ($cartItem) {
            if ($cartItem->getQuantity() <= 1) {
                $em->remove($cartItem);
            } else {
                $cartItem->setQuantity($cartItem->getQuantity() - 1);
            }
            $em->flush();
        }
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/cart/remove/{id}', name: 'cart_remove', methods: ['GET'])]
    public function remove(int $id, EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cartItem = $this->findOwnCartItem($id, $em, $session);
        if ($cartItem) {
            $em->remove($cartItem);
            $em->flush();
        }

        return $this->redirectToRoute('cart_index');
    }

    /**
     * Récupère une ligne de panier par son id, en vérifiant qu'elle appartient
     * bien au panier de la session courante.
     */
    private function findOwnCartItem(int $id, EntityManagerInterface $em, SessionInterface $session): ?CartItem
    {
        $cart = $this->getCurrentCart($em, $session);
        if (!$cart) {
            return null;
        }

        $cartItem = $em->getRepository(CartItem::class)->find($id);

        return ($cartItem && $cartItem->getCart() === $cart) ? $cartItem : null;
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
            // Le point relais n'a de sens que pour Mondial Relay
            if ($mode !== 'mondial_relay') {
                $session->remove('relay_point');
            }
        }

        // Le choix de la livraison se fait sur la page Livraison du tunnel de commande
        return $this->redirectToRoute('checkout_shipping');
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

    private function findOrCreateCartItem(Cart $cart, Article $article, array $options, EntityManagerInterface $em): CartItem
    {
        // Une ligne = un article + ses options (couleur / modèle) : on ne fusionne
        // que si les options identifiantes sont identiques
        $candidates = $em->getRepository(CartItem::class)->findBy([
            'cart'    => $cart,
            'article' => $article,
        ]);

        $wantedColor = $options['color'] ?? null;
        $wantedModel = $options['model'] ?? null;

        foreach ($candidates as $candidate) {
            $candidateOptions = $candidate->getOptions() ?? [];
            if (($candidateOptions['color'] ?? null) === $wantedColor
                && ($candidateOptions['model'] ?? null) === $wantedModel) {
                return $candidate;
            }
        }

        $cartItem = new CartItem();
        $cartItem->setCart($cart);
        $cartItem->setArticle($article);
        $cartItem->setQuantity(0);
        if (!empty($options)) {
            $cartItem->setOptions($options);
        }

        return $cartItem;
    }
}
