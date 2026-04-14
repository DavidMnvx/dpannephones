<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Commande;
use App\Entity\CommandeItem;
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

        if ($categorie && array_key_exists($categorie, self::CATEGORIES)) {
            $articles = $em->getRepository(Article::class)->findBy(['categorie' => $categorie]);
        } else {
            $categorie = null;
            $articles = $em->getRepository(Article::class)->findAll();
        }

        $cart = $this->getCurrentCart($em, $session);

        return $this->render('boutique/index.html.twig', [
            'articles'         => $articles,
            'cart'             => $cart ? $cart->getItems() : [],
            'currentCategorie' => $categorie,
            'categories'       => self::CATEGORIES,
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
    public function cartIndex(EntityManagerInterface $em, SessionInterface $session): Response
    {
        $cart = $this->getCurrentCart($em, $session);
        $items = $cart ? $cart->getItems() : [];

        $total = 0;
        foreach ($items as $item) {
            $total += $item->getArticle()->getPrice() * $item->getQuantity();
        }

        return $this->render('cart/index.html.twig', [
            'cart'  => $items,
            'total' => $total,
        ]);
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
