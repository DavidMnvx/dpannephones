<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\User;
use App\Form\ProfileType;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    #[Route('/mon-compte', name: 'account_index')]
    public function index(EntityManagerInterface $em, ReviewRepository $reviewRepo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $commandes = $em->getRepository(Commande::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        // Séparer commandes en cours / terminées
        $statutsEnCours    = ['en_attente', 'payee', 'en_preparation', 'expediee'];
        $statutsTerminees  = ['livree', 'retiree', 'annulee'];

        $commandesEnCours   = array_values(array_filter($commandes, fn($c) => in_array($c->getStatut(), $statutsEnCours)));
        $commandesTerminees = array_values(array_filter($commandes, fn($c) => in_array($c->getStatut(), $statutsTerminees)));

        // Statistiques rapides
        $totalDepense = 0;
        $commandesPayees = 0;
        foreach ($commandes as $c) {
            if (in_array($c->getStatut(), ['payee', 'expediee', 'livree', 'retiree'])) {
                $totalDepense += $c->getTotal();
                $commandesPayees++;
            }
        }

        // Liste des IDs de commandes déjà notées par l'utilisateur
        $reviewedCommandeIds = [];
        foreach ($reviewRepo->findByUser($user) as $r) {
            if ($r->getCommande()) {
                $reviewedCommandeIds[] = $r->getCommande()->getId();
            }
        }

        // Formulaire d'édition du profil (pré-rempli, non lié à la requête ici)
        $profileForm = $this->createForm(ProfileType::class, $user, [
            'action' => $this->generateUrl('account_edit'),
            'method' => 'POST',
        ]);

        return $this->render('account/index.html.twig', [
            'user'                => $user,
            'commandes'           => $commandes,
            'commandesEnCours'    => $commandesEnCours,
            'commandesTerminees'  => $commandesTerminees,
            'totalDepense'        => $totalDepense,
            'commandesPayees'     => $commandesPayees,
            'profileForm'         => $profileForm->createView(),
            'reviewedCommandeIds' => $reviewedCommandeIds,
        ]);
    }

    #[Route('/mon-compte/modifier', name: 'account_edit', methods: ['POST'])]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Vos informations ont été mises à jour.');
        } else {
            $this->addFlash('error', 'Veuillez corriger les erreurs dans le formulaire.');
        }

        return $this->redirectToRoute('account_index');
    }
}
