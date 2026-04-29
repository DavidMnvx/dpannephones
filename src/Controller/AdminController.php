<?php
namespace App\Controller;

use App\Entity\Article;
use App\Entity\Commande;
use App\Entity\GoogleReview;
use App\Entity\AppSetting;
use App\Entity\Marque;
use App\Entity\Partenaire;
use App\Entity\PromoCode;
use App\Entity\Review;
use App\Entity\SiteImage;
use App\Entity\SocialLink;
use App\Entity\User;
use App\Repository\AppSettingRepository;
use App\Repository\CommandeRepository;
use App\Repository\PromoCodeRepository;
use App\Repository\ReviewRepository;
use App\Repository\SocialLinkRepository;
use App\Repository\UserRepository;
use App\Entity\Model;
use App\Entity\Reparation;
use App\Form\AdminClientType;
use App\Form\GoogleReviewType;
use App\Form\MarqueType;
use App\Form\ModelType;
use App\Form\PartenaireType;
use App\Form\PromoCodeType;
use App\Form\ReparationType;
use App\Form\SiteImageType;
use App\Form\SocialLinkType;
use App\Service\ReviewNotificationService;
use App\Service\CommandeNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_index')]
    public function index(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, CommandeRepository $commandeRepo): Response
    {
        $marque = new Marque();
        $model = new Model();
        $reparation = new Reparation();

        // Pré-remplir le modèle depuis la session si on vient de créer une réparation
        // (permet de chaîner les saisies pour le même modèle sans le re-sélectionner)
        $lastModelId = $request->getSession()->get('admin_last_reparation_model_id');
        if ($lastModelId) {
            $lastModel = $entityManager->getRepository(Model::class)->find($lastModelId);
            if ($lastModel) {
                $reparation->setModel($lastModel);
            }
        }

        $marqueForm = $this->createForm(MarqueType::class, $marque);
        $modelForm = $this->createForm(ModelType::class, $model);
        $reparationForm = $this->createForm(ReparationType::class, $reparation);

        $marqueForm->handleRequest($request);
        $modelForm->handleRequest($request);
        $reparationForm->handleRequest($request);

        if ($marqueForm->isSubmitted() && $marqueForm->isValid()) {
            $imageFile = $marqueForm->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Gérer l'exception si nécessaire
                }

                $marque->setImage($newFilename);
            }

            $entityManager->persist($marque);
            $entityManager->flush();
            $this->addFlash('success', 'Marque ajoutée avec succès!');

            return $this->redirectToRoute('admin_index');
        }

        if ($modelForm->isSubmitted() && $modelForm->isValid()) {
            $imageFile = $modelForm->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // Gérer l'exception si nécessaire
                }

                $model->setImage($newFilename);
            }

            $entityManager->persist($model);
            $entityManager->flush();
            $this->addFlash('success', 'Modèle ajouté avec succès!');

            return $this->redirectToRoute('admin_index');
        }

        if ($reparationForm->isSubmitted() && $reparationForm->isValid()) {
            // Cohérence : si "service commun" coché, on annule le modèle (et inversement)
            if ($reparation->isUniversal()) {
                $reparation->setModel(null);
            }

            // Si l'admin laisse l'ordre à 0 (valeur par défaut), on l'assigne automatiquement
            // à la fin de la liste : max + 10 dans le bon scope (modèle concerné OU universelles)
            if ($reparation->getSortOrder() === 0) {
                $qb = $entityManager->createQueryBuilder()
                    ->select('COALESCE(MAX(r.sortOrder), 0)')
                    ->from(Reparation::class, 'r');

                if ($reparation->isUniversal()) {
                    $qb->where('r.isUniversal = :true')->setParameter('true', true);
                } elseif ($reparation->getModel()) {
                    $qb->where('r.model = :model')->setParameter('model', $reparation->getModel());
                } else {
                    $qb->where('r.model IS NULL AND r.isUniversal = :false')->setParameter('false', false);
                }

                $maxOrder = (int) $qb->getQuery()->getSingleScalarResult();
                $reparation->setSortOrder($maxOrder + 10);
            }

            $entityManager->persist($reparation);
            $entityManager->flush();

            $scopeLabel = $reparation->isUniversal()
                ? 'tous les modèles (service commun)'
                : ($reparation->getModel() ? $reparation->getModel()->getName() : '—');
            $this->addFlash('success', sprintf(
                '✅ Réparation "%s" ajoutée pour %s. Tu peux en saisir une autre 👇',
                $reparation->getName(),
                $scopeLabel
            ));

            // Mémoriser le modèle pour pré-remplir au prochain affichage (sauf si universel)
            if (!$reparation->isUniversal() && $reparation->getModel()) {
                $request->getSession()->set('admin_last_reparation_model_id', $reparation->getModel()->getId());
            }

            // Rester sur la même page (ancre #reparations) pour pouvoir en saisir une autre
            return $this->redirectToRoute('admin_index', ['_fragment' => 'reparations']);
        }

        $marques     = $entityManager->getRepository(Marque::class)->findAll();
        $models      = $entityManager->getRepository(Model::class)->findAll();
        $reparations = $entityManager->getRepository(Reparation::class)->findAllOrdered();
        $reparationsByModelAll = $entityManager->getRepository(Reparation::class)->findGroupedByModel();
        $reparationsUniverselles = $entityManager->getRepository(Reparation::class)->findUniversalReparations();
        $articles    = $entityManager->getRepository(Article::class)->findAll();

        // ─── Onglets par marque + pagination pour la liste réparations ───
        // 1) Compter le nombre de réparations par marque (sur la liste complète)
        $marquesRep = [];
        foreach ($reparationsByModelAll as $group) {
            $marqueObj  = $group['model']->getMarque();
            $marqueId   = $marqueObj ? $marqueObj->getId() : 0;
            $marqueName = $marqueObj ? $marqueObj->getName() : 'Sans marque';
            if (!isset($marquesRep[$marqueId])) {
                $marquesRep[$marqueId] = ['id' => $marqueId, 'name' => $marqueName, 'count' => 0, 'models' => 0];
            }
            $marquesRep[$marqueId]['count']  += count($group['items']);
            $marquesRep[$marqueId]['models'] += 1;
        }
        $marquesRep = array_values($marquesRep);
        usort($marquesRep, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        // 2) Filtre par marque (?marque_rep=X)
        $selectedMarqueRep = $request->query->getInt('marque_rep', 0);
        $reparationsByModelFiltered = $reparationsByModelAll;
        if ($selectedMarqueRep > 0) {
            $reparationsByModelFiltered = array_filter(
                $reparationsByModelAll,
                function ($g) use ($selectedMarqueRep) {
                    $m = $g['model']->getMarque();
                    return $m && $m->getId() === $selectedMarqueRep;
                }
            );
        }

        // 3) Pagination : 15 modèles / page
        $perPageRep    = 15;
        $totalGroups   = count($reparationsByModelFiltered);
        $totalPagesRep = max(1, (int) ceil($totalGroups / $perPageRep));
        $currentPageRep = max(1, min($totalPagesRep, $request->query->getInt('page_rep', 1)));
        $reparationsByModel = array_slice(
            array_values($reparationsByModelFiltered),
            ($currentPageRep - 1) * $perPageRep,
            $perPageRep
        );

        $annee         = (int) date('Y');
        $ventesParMois = $commandeRepo->getVentesParMois($annee);
        $totalAnnee    = $commandeRepo->getTotalAnnee($annee);
        $commandes     = $entityManager->getRepository(Commande::class)->findBy([], ['createdAt' => 'DESC'], 10);

        // Stats Commandes
        $allCommandes    = $entityManager->getRepository(Commande::class)->findAll();
        $nbCommandes     = count($allCommandes);
        $commandeCounts  = [];
        foreach ($allCommandes as $c) {
            $s = $c->getStatut();
            $commandeCounts[$s] = ($commandeCounts[$s] ?? 0) + 1;
        }

        // Commandes à expédier (en préparation)
        $commandesAExpedier = $entityManager->getRepository(Commande::class)->findBy(
            ['statut' => 'en_preparation'],
            ['createdAt' => 'ASC']
        );

        // Stats Clients
        $allUsers   = $entityManager->getRepository(User::class)->findAll();
        $nbClients  = count($allUsers);
        $nbVerifies = count(array_filter($allUsers, fn($u) => $u->isVerified()));

        // Stats Avis
        $reviewRepo      = $entityManager->getRepository(Review::class);
        $nbReviewsTotal   = $reviewRepo->count([]);
        $nbReviewsPending = $reviewRepo->count(['status' => Review::STATUS_PENDING]);
        $nbReviewsPublished = $reviewRepo->count(['status' => Review::STATUS_APPROVED]);
        $avgReviewRating  = $reviewRepo->getAverageRating();

        // Top 5 articles les plus vendus
        $bestSellers       = $commandeRepo->getBestSellers(5);
        $totalArticlesVendus = $commandeRepo->getTotalArticlesVendus();

        return $this->render('admin/index.html.twig', [
            'marqueForm'    => $marqueForm->createView(),
            'modelForm'     => $modelForm->createView(),
            'reparationForm'=> $reparationForm->createView(),
            'marques'       => $marques,
            'models'        => $models,
            'reparations'   => $reparations,
            'reparationsByModel' => $reparationsByModel,
            'reparationsUniverselles' => $reparationsUniverselles,
            'marquesRep'         => $marquesRep,
            'selectedMarqueRep'  => $selectedMarqueRep,
            'currentPageRep'     => $currentPageRep,
            'totalPagesRep'      => $totalPagesRep,
            'totalGroupsRep'     => $totalGroups,
            'articles'      => $articles,
            'ventesParMois' => $ventesParMois,
            'totalAnnee'    => $totalAnnee,
            'commandes'     => $commandes,
            'annee'         => $annee,
            'nbCommandes'   => $nbCommandes,
            'commandeCounts'=> $commandeCounts,
            'nbClients'          => $nbClients,
            'nbVerifies'         => $nbVerifies,
            'commandesAExpedier' => $commandesAExpedier,
            'nbReviewsTotal'     => $nbReviewsTotal,
            'nbReviewsPending'   => $nbReviewsPending,
            'nbReviewsPublished' => $nbReviewsPublished,
            'avgReviewRating'    => $avgReviewRating,
            'bestSellers'        => $bestSellers,
            'totalArticlesVendus' => $totalArticlesVendus,
        ]);
    }

    #[Route('/admin/marque/edit/{id}', name: 'admin_marque_edit')]
    public function editMarque(Request $request, Marque $marque, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MarqueType::class, $marque);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('admin_index');
        }

        return $this->render('admin/marque/edit.html.twig', [
            'form' => $form->createView(),
            'marque' => $marque,
        ]);
    }

    #[Route('/admin/marque/delete/{id}', name: 'admin_marque_delete')]
    public function deleteMarque(Marque $marque, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($marque);
        $entityManager->flush();

        return $this->redirectToRoute('admin_index');
    }



#[Route('/admin/modele/edit/{id}', name: 'admin_modele_edit')]
public function editModele(Request $request, Model $model, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
{
    $form = $this->createForm(ModelType::class, $model);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $imageFile = $form->get('image')->getData();

        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move(
                    $this->getParameter('images_directory'),
                    $newFilename
                );
            } catch (FileException $e) {
                // Gérer l'exception si nécessaire
            }

            $model->setImage($newFilename);
        }

        $entityManager->flush();
        $this->addFlash('success', 'Modèle édité avec succès!');

        return $this->redirectToRoute('admin_index');
    }

    return $this->render('admin/modele/edit.html.twig', [
        'form' => $form->createView(),
        'model' => $model,
    ]);
}

#[Route('/admin/modele/delete/{id}', name: 'admin_modele_delete')]
public function deleteModele(Model $model, EntityManagerInterface $entityManager): Response
{
    $entityManager->remove($model);
    $entityManager->flush();
    $this->addFlash('success', 'Modèle supprimé avec succès!');

    return $this->redirectToRoute('admin_index');
}

#[Route('/admin/reparation/clear-memory', name: 'admin_reparation_clear_memory')]
public function clearReparationMemory(Request $request): Response
{
    $request->getSession()->remove('admin_last_reparation_model_id');
    $this->addFlash('info', 'Mémoire du dernier modèle effacée. Tu peux maintenant choisir un autre modèle.');
    return $this->redirectToRoute('admin_index', ['_fragment' => 'reparations']);
}

/**
 * Renumérote automatiquement toutes les réparations à sortOrder = 0 :
 * pour chaque modèle concerné, on les place à la fin (max + 10, +20…).
 * Les réparations déjà numérotées ne sont pas touchées.
 */
#[Route('/admin/reparation/fix-zero-orders', name: 'admin_reparation_fix_zeros', methods: ['POST'])]
public function fixZeroOrders(Request $request, EntityManagerInterface $em): Response
{
    if (!$this->isCsrfTokenValid('fix_zero_orders', $request->request->get('_token'))) {
        $this->addFlash('error', 'Jeton CSRF invalide.');
        return $this->redirectToRoute('admin_index', ['_fragment' => 'reparations']);
    }

    $repRepo = $em->getRepository(Reparation::class);

    // Toutes les réparations avec sortOrder = 0
    $zeroReps = $repRepo->createQueryBuilder('r')
        ->where('r.sortOrder = 0')
        ->getQuery()
        ->getResult();

    if (empty($zeroReps)) {
        $this->addFlash('info', 'Aucune réparation à réparer (toutes ont déjà un ordre > 0).');
        return $this->redirectToRoute('admin_index', ['_fragment' => 'reparations']);
    }

    // Grouper par modèle pour leur attribuer un sort_order continu
    $byModel = [];
    foreach ($zeroReps as $rep) {
        if (!$rep->getModel()) continue;
        $modelId = $rep->getModel()->getId();
        $byModel[$modelId][] = $rep;
    }

    $fixed = 0;
    foreach ($byModel as $modelId => $reps) {
        // Récupérer le max actuel pour ce modèle (parmi les non-zéro)
        $maxOrder = (int) $em->createQueryBuilder()
            ->select('COALESCE(MAX(r.sortOrder), 0)')
            ->from(Reparation::class, 'r')
            ->where('r.model = :model')
            ->andWhere('r.sortOrder > 0')
            ->setParameter('model', $reps[0]->getModel())
            ->getQuery()
            ->getSingleScalarResult();

        // Assigner max+10, max+20, max+30…
        foreach ($reps as $i => $rep) {
            $rep->setSortOrder($maxOrder + (($i + 1) * 10));
            $fixed++;
        }
    }

    $em->flush();
    $this->addFlash('success', sprintf(
        '✅ %d réparation%s renumérotée%s automatiquement (placées à la fin de chaque modèle).',
        $fixed, $fixed > 1 ? 's' : '', $fixed > 1 ? 's' : ''
    ));

    return $this->redirectToRoute('admin_index', ['_fragment' => 'reparations']);
}

/**
 * Page dédiée pour réordonner les réparations d'un modèle donné.
 * URL : /admin/reparations/reordonner?model_id=X
 */
#[Route('/admin/reparations/reordonner', name: 'admin_reparation_reorder', methods: ['GET', 'POST'])]
public function reorderReparations(Request $request, EntityManagerInterface $em): Response
{
    // Liste des modèles qui ont au moins une réparation (pour peupler le sélecteur)
    $modelsWithReps = $em->createQueryBuilder()
        ->select('m', 'mq', 'COUNT(r.id) as nbReps')
        ->from(Model::class, 'm')
        ->leftJoin('m.marque', 'mq')
        ->innerJoin('m.reparations', 'r')
        ->groupBy('m.id')
        ->orderBy('mq.name', 'ASC')
        ->addOrderBy('m.name', 'ASC')
        ->getQuery()
        ->getResult();

    $modelId = $request->query->get('model_id') ?? $request->request->get('model_id');
    $selectedModel = null;
    $reparations = [];

    if ($modelId) {
        $selectedModel = $em->getRepository(Model::class)->find($modelId);
        if ($selectedModel) {
            $reparations = $em->getRepository(Reparation::class)->findBy(
                ['model' => $selectedModel],
                ['sortOrder' => 'ASC', 'name' => 'ASC']
            );
        }
    }

    // Traitement du formulaire de sauvegarde de l'ordre
    if ($request->isMethod('POST') && $selectedModel) {
        if (!$this->isCsrfTokenValid('reorder_reparations', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_reparation_reorder', ['model_id' => $modelId]);
        }

        $orders = $request->request->all('order') ?: [];
        $updated = 0;

        foreach ($orders as $repId => $order) {
            $rep = $em->getRepository(Reparation::class)->find((int) $repId);
            if ($rep && $rep->getModel()?->getId() === $selectedModel->getId()) {
                $rep->setSortOrder((int) $order);
                $updated++;
            }
        }

        $em->flush();
        $this->addFlash('success', sprintf(
            '✅ Ordre mis à jour pour %d réparation%s du modèle "%s".',
            $updated,
            $updated > 1 ? 's' : '',
            $selectedModel->getName()
        ));

        return $this->redirectToRoute('admin_reparation_reorder', ['model_id' => $modelId]);
    }

    return $this->render('admin/reparation/reorder.html.twig', [
        'modelsWithReps' => $modelsWithReps,
        'selectedModel'  => $selectedModel,
        'reparations'    => $reparations,
    ]);
}

#[Route('/admin/reparation/edit/{id}', name: 'admin_reparation_edit')]
public function editReparation(Request $request, Reparation $reparation, EntityManagerInterface $entityManager): Response
{
    $form = $this->createForm(ReparationType::class, $reparation);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $entityManager->flush();
        $this->addFlash('success', 'Réparation éditée avec succès!');

        return $this->redirectToRoute('admin_index');
    }

    return $this->render('admin/reparation/edit.html.twig', [
        'form' => $form->createView(),
        'reparation' => $reparation,
    ]);
}

#[Route('/admin/reparation/delete/{id}', name: 'admin_reparation_delete')]
public function deleteReparation(Reparation $reparation, EntityManagerInterface $entityManager): Response
{
    $entityManager->remove($reparation);
    $entityManager->flush();
    $this->addFlash('success', 'Réparation supprimée avec succès!');

    return $this->redirectToRoute('admin_index');
}

// ───────────────────────────────────────────────
// GESTION DES COMMANDES
// ───────────────────────────────────────────────

#[Route('/admin/commandes', name: 'admin_commandes')]
public function commandes(Request $request, EntityManagerInterface $em): Response
{
    $statut = $request->query->get('statut', '');
    $criteria = $statut ? ['statut' => $statut] : [];
    $commandes = $em->getRepository(Commande::class)->findBy($criteria, ['createdAt' => 'DESC']);

    // Compteurs par statut
    $allCommandes = $em->getRepository(Commande::class)->findAll();
    $counts = [];
    foreach ($allCommandes as $c) {
        $s = $c->getStatut();
        $counts[$s] = ($counts[$s] ?? 0) + 1;
    }

    return $this->render('admin/commandes/index.html.twig', [
        'commandes' => $commandes,
        'statutFiltre' => $statut,
        'counts' => $counts,
        'total' => count($allCommandes),
    ]);
}

#[Route('/admin/commandes/{id}', name: 'admin_commande_show', requirements: ['id' => '\d+'])]
public function commandeShow(Commande $commande): Response
{
    $statutsSuivants = [
        'en_attente'    => [],
        'payee'         => ['en_preparation', 'annulee'],
        'en_preparation'=> ['expediee', 'retiree', 'annulee'],
        'expediee'      => ['livree'],
        'livree'        => [],
        'retiree'       => [],
        'annulee'       => [],
    ];

    return $this->render('admin/commandes/show.html.twig', [
        'commande' => $commande,
        'statutsSuivants' => $statutsSuivants[$commande->getStatut()] ?? [],
    ]);
}

#[Route('/admin/commandes/{id}/statut', name: 'admin_commande_statut', methods: ['POST'], requirements: ['id' => '\d+'])]
public function commandeStatut(Commande $commande, Request $request, EntityManagerInterface $em, CommandeNotificationService $notif): Response
{
    $newStatut = $request->request->get('statut');
    $valides   = ['en_attente', 'payee', 'en_preparation', 'expediee', 'livree', 'retiree', 'annulee'];

    if (in_array($newStatut, $valides, true)) {
        $commande->setStatut($newStatut);

        // Numéro de suivi et transporteur lors de l'expédition
        if ($newStatut === 'expediee') {
            $numeroSuivi  = trim($request->request->get('numero_suivi', ''));
            $transporteur = trim($request->request->get('transporteur', ''));
            if ($numeroSuivi)  $commande->setNumeroSuivi($numeroSuivi);
            if ($transporteur) $commande->setTransporteur($transporteur);
        }

        $em->flush();

        // Notification email au client
        try {
            $notif->notifyStatutChange($commande);
        } catch (\Exception $e) {
            // L'email échoue silencieusement — la mise à jour est quand même sauvegardée
        }

        $this->addFlash('success', 'Statut mis à jour.' . ($commande->getUser() ? ' Email de notification envoyé.' : ''));
    } else {
        $this->addFlash('error', 'Statut invalide.');
    }

    return $this->redirectToRoute('admin_commande_show', ['id' => $commande->getId()]);
}

// ───────────────────────────────────────────────
// GESTION DES CLIENTS
// ───────────────────────────────────────────────

#[Route('/admin/clients', name: 'admin_clients')]
public function clients(EntityManagerInterface $em): Response
{
    $users = $em->getRepository(User::class)->findBy([], ['id' => 'DESC']);

    // Enrichir avec stats commandes
    $stats = [];
    foreach ($users as $user) {
        $commandes = $em->getRepository(Commande::class)->findBy(['user' => $user]);
        $totalDepense = array_sum(array_map(fn($c) => $c->getTotal(), array_filter($commandes, fn($c) => in_array($c->getStatut(), ['payee', 'en_preparation', 'expediee', 'livree', 'retiree']))));
        $stats[$user->getId()] = [
            'nbCommandes' => count($commandes),
            'totalDepense' => $totalDepense,
        ];
    }

    return $this->render('admin/clients/index.html.twig', [
        'users' => $users,
        'stats' => $stats,
    ]);
}

#[Route('/admin/clients/{id}', name: 'admin_client_show', requirements: ['id' => '\d+'])]
public function clientShow(User $user, EntityManagerInterface $em): Response
{
    $commandes = $em->getRepository(Commande::class)->findBy(['user' => $user], ['createdAt' => 'DESC']);
    $totalDepense = array_sum(array_map(fn($c) => $c->getTotal(), array_filter($commandes, fn($c) => in_array($c->getStatut(), ['payee', 'en_preparation', 'expediee', 'livree', 'retiree']))));

    return $this->render('admin/clients/show.html.twig', [
        'user'         => $user,
        'commandes'    => $commandes,
        'totalDepense' => $totalDepense,
    ]);
}

#[Route('/admin/clients/{id}/modifier', name: 'admin_client_edit', requirements: ['id' => '\d+'])]
public function clientEdit(User $user, Request $request, EntityManagerInterface $em): Response
{
    $form = $this->createForm(AdminClientType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();
        $this->addFlash('success', 'Les informations du client ont été mises à jour.');
        return $this->redirectToRoute('admin_client_show', ['id' => $user->getId()]);
    }

    return $this->render('admin/clients/edit.html.twig', [
        'user' => $user,
        'form' => $form->createView(),
    ]);
}

#[Route('/admin/clients/{id}/supprimer', name: 'admin_client_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
public function clientDelete(User $user, Request $request, EntityManagerInterface $em): Response
{
    if ($this->isCsrfTokenValid('delete_client_' . $user->getId(), $request->request->get('_token'))) {
        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'Le client ' . $user->getPrenom() . ' ' . $user->getNom() . ' a été supprimé.');
    } else {
        $this->addFlash('error', 'Token de sécurité invalide.');
    }

    return $this->redirectToRoute('admin_clients');
}

// ───────────────────────────────────────────────
// GESTION DES PARTENAIRES
// ───────────────────────────────────────────────

#[Route('/admin/partenaires', name: 'admin_partenaires')]
public function partenaires(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
{
    $partenaire = new Partenaire();
    $form = $this->createForm(PartenaireType::class, $partenaire);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $imageFile = $form->get('image')->getData();

        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move(
                    $this->getParameter('images_directory'),
                    $newFilename
                );
            } catch (FileException $e) {
                // silent
            }

            $partenaire->setImage($newFilename);
        }

        // Valeurs par défaut si non fournies
        if ($partenaire->getSortOrder() === null) {
            $partenaire->setSortOrder(0);
        }
        if ($partenaire->getIsActive() === null) {
            $partenaire->setIsActive(true);
        }

        $em->persist($partenaire);
        $em->flush();
        $this->addFlash('success', 'Partenaire ajouté avec succès !');

        return $this->redirectToRoute('admin_partenaires');
    }

    $partenaires = $em->getRepository(Partenaire::class)->findBy([], ['type' => 'ASC', 'sortOrder' => 'ASC', 'name' => 'ASC']);

    return $this->render('admin/partenaire/index.html.twig', [
        'form'        => $form->createView(),
        'partenaires' => $partenaires,
    ]);
}

#[Route('/admin/partenaires/edit/{id}', name: 'admin_partenaire_edit', requirements: ['id' => '\d+'])]
public function editPartenaire(Request $request, Partenaire $partenaire, EntityManagerInterface $em, SluggerInterface $slugger): Response
{
    $form = $this->createForm(PartenaireType::class, $partenaire);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $imageFile = $form->get('image')->getData();

        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move(
                    $this->getParameter('images_directory'),
                    $newFilename
                );
            } catch (FileException $e) {
                // silent
            }

            $partenaire->setImage($newFilename);
        }

        $em->flush();
        $this->addFlash('success', 'Partenaire modifié avec succès !');

        return $this->redirectToRoute('admin_partenaires');
    }

    return $this->render('admin/partenaire/edit.html.twig', [
        'form'       => $form->createView(),
        'partenaire' => $partenaire,
    ]);
}

#[Route('/admin/partenaires/delete/{id}', name: 'admin_partenaire_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
public function deletePartenaire(Partenaire $partenaire, Request $request, EntityManagerInterface $em): Response
{
    if ($this->isCsrfTokenValid('delete_partenaire_' . $partenaire->getId(), $request->request->get('_token'))) {
        $em->remove($partenaire);
        $em->flush();
        $this->addFlash('success', 'Partenaire supprimé.');
    } else {
        $this->addFlash('error', 'Token de sécurité invalide.');
    }

    return $this->redirectToRoute('admin_partenaires');
}

#[Route('/admin/partenaires/toggle/{id}', name: 'admin_partenaire_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
public function togglePartenaire(Partenaire $partenaire, Request $request, EntityManagerInterface $em): Response
{
    if ($this->isCsrfTokenValid('toggle_partenaire_' . $partenaire->getId(), $request->request->get('_token'))) {
        $partenaire->setIsActive(!$partenaire->isActive());
        $em->flush();
        $this->addFlash('success', $partenaire->isActive() ? 'Partenaire activé.' : 'Partenaire désactivé.');
    }

    return $this->redirectToRoute('admin_partenaires');
}

// ───────────────────────────────────────────────
// AVIS GOOGLE (saisie manuelle par l'admin)
// ───────────────────────────────────────────────

#[Route('/admin/avis-google', name: 'admin_google_reviews')]
public function googleReviews(Request $request, EntityManagerInterface $em): Response
{
    $review = new GoogleReview();
    $form   = $this->createForm(GoogleReviewType::class, $review);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        if ($review->getSortOrder() === null) {
            $review->setSortOrder(0);
        }
        $em->persist($review);
        $em->flush();
        $this->addFlash('success', 'Avis Google ajouté avec succès !');
        return $this->redirectToRoute('admin_google_reviews');
    }

    $reviews = $em->getRepository(GoogleReview::class)->findBy([], ['sortOrder' => 'ASC', 'createdAt' => 'DESC']);

    return $this->render('admin/google_review/index.html.twig', [
        'form'    => $form->createView(),
        'reviews' => $reviews,
    ]);
}

#[Route('/admin/avis-google/edit/{id}', name: 'admin_google_review_edit', requirements: ['id' => '\d+'])]
public function editGoogleReview(Request $request, GoogleReview $review, EntityManagerInterface $em): Response
{
    $form = $this->createForm(GoogleReviewType::class, $review);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();
        $this->addFlash('success', 'Avis modifié avec succès.');
        return $this->redirectToRoute('admin_google_reviews');
    }

    return $this->render('admin/google_review/edit.html.twig', [
        'form'   => $form->createView(),
        'review' => $review,
    ]);
}

#[Route('/admin/avis-google/delete/{id}', name: 'admin_google_review_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
public function deleteGoogleReview(GoogleReview $review, Request $request, EntityManagerInterface $em): Response
{
    if ($this->isCsrfTokenValid('delete_google_review_' . $review->getId(), $request->request->get('_token'))) {
        $em->remove($review);
        $em->flush();
        $this->addFlash('success', 'Avis supprimé.');
    } else {
        $this->addFlash('error', 'Token de sécurité invalide.');
    }

    return $this->redirectToRoute('admin_google_reviews');
}

#[Route('/admin/avis-google/toggle/{id}', name: 'admin_google_review_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
public function toggleGoogleReview(GoogleReview $review, Request $request, EntityManagerInterface $em): Response
{
    if ($this->isCsrfTokenValid('toggle_google_review_' . $review->getId(), $request->request->get('_token'))) {
        $review->setIsActive(!$review->isActive());
        $em->flush();
        $this->addFlash('success', $review->isActive() ? 'Avis activé.' : 'Avis désactivé.');
    }

    return $this->redirectToRoute('admin_google_reviews');
}

// ───────────────────────────────────────────────
// MODÉRATION AVIS CLIENTS (Review)
// ───────────────────────────────────────────────

#[Route('/admin/avis-clients', name: 'admin_client_reviews')]
public function clientReviews(Request $request, ReviewRepository $repo): Response
{
    $filter = $request->query->get('filter', 'pending');

    $reviews = match ($filter) {
        'approved' => $repo->findBy(['status' => Review::STATUS_APPROVED], ['publishedAt' => 'DESC']),
        'rejected' => $repo->findBy(['status' => Review::STATUS_REJECTED], ['createdAt' => 'DESC']),
        'all'      => $repo->findBy([], ['createdAt' => 'DESC']),
        default    => $repo->findPending(),
    };

    $counts = [
        'pending'  => $repo->count(['status' => Review::STATUS_PENDING]),
        'approved' => $repo->count(['status' => Review::STATUS_APPROVED]),
        'rejected' => $repo->count(['status' => Review::STATUS_REJECTED]),
        'all'      => $repo->count([]),
    ];

    return $this->render('admin/review/index.html.twig', [
        'reviews' => $reviews,
        'filter'  => $filter,
        'counts'  => $counts,
    ]);
}

#[Route('/admin/avis-clients/{id}/approve', name: 'admin_review_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
public function approveReview(Review $review, Request $request, EntityManagerInterface $em, ReviewNotificationService $notif): Response
{
    if ($this->isCsrfTokenValid('review_approve_' . $review->getId(), $request->request->get('_token'))) {
        $review->setStatus(Review::STATUS_APPROVED);
        $em->flush();

        try {
            $notif->notifyUserReviewApproved($review);
        } catch (\Exception $e) {
            // silencieux
        }

        $this->addFlash('success', 'Avis approuvé et publié sur le site. Le client a été notifié.');
    }

    return $this->redirectToRoute('admin_client_reviews');
}

#[Route('/admin/avis-clients/{id}/reject', name: 'admin_review_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
public function rejectReview(Review $review, Request $request, EntityManagerInterface $em): Response
{
    if ($this->isCsrfTokenValid('review_reject_' . $review->getId(), $request->request->get('_token'))) {
        $review->setStatus(Review::STATUS_REJECTED);
        $em->flush();
        $this->addFlash('success', 'Avis rejeté (non publié).');
    }

    return $this->redirectToRoute('admin_client_reviews');
}

#[Route('/admin/avis-clients/{id}/respond', name: 'admin_review_respond', methods: ['POST'], requirements: ['id' => '\d+'])]
public function respondReview(Review $review, Request $request, EntityManagerInterface $em): Response
{
    if ($this->isCsrfTokenValid('review_respond_' . $review->getId(), $request->request->get('_token'))) {
        $response = trim((string) $request->request->get('admin_response', ''));
        $review->setAdminResponse($response ?: null);
        $em->flush();
        $this->addFlash('success', 'Réponse enregistrée.');
    }

    return $this->redirectToRoute('admin_client_reviews');
}

#[Route('/admin/avis-clients/{id}/delete', name: 'admin_review_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
public function deleteReview(Review $review, Request $request, EntityManagerInterface $em): Response
{
    if ($this->isCsrfTokenValid('review_delete_' . $review->getId(), $request->request->get('_token'))) {
        $em->remove($review);
        $em->flush();
        $this->addFlash('success', 'Avis supprimé définitivement.');
    }

    return $this->redirectToRoute('admin_client_reviews');
}

// ───────────────────────────────────────────────
// IMAGES DU SITE (photos modifiables sans dev)
// ───────────────────────────────────────────────

/**
 * Libellés humains + icônes des catégories d'images, dans l'ordre d'affichage.
 */
private const SITE_IMAGE_CATEGORIES = [
    'home' => [
        'label' => 'Page d\'accueil',
        'icon'  => 'fas fa-home',
        'description' => 'Carrousel hero et photo de la boutique sur la page d\'accueil.',
    ],
    'pages_principales' => [
        'label' => 'Pages principales',
        'icon'  => 'fas fa-star',
        'description' => 'Bannières des pages vitrines (boutique, réparations, avis, partenaires, contact).',
    ],
    'ordinateur' => [
        'label' => 'Page Ordinateur',
        'icon'  => 'fas fa-laptop',
        'description' => 'Bannière + 5 illustrations de la page /ordinateur (SSD, RAM, PC Gamer, PC Bureau, Transfert).',
    ],
    'legal' => [
        'label' => 'Pages légales',
        'icon'  => 'fas fa-balance-scale',
        'description' => 'Bannières des pages CGV, confidentialité et mentions légales.',
    ],
    'utilitaires' => [
        'label' => 'Pages utilitaires',
        'icon'  => 'fas fa-cubes',
        'description' => 'Bannières des pages secondaires : panier, connexion, compte, paiement, qui sommes-nous.',
    ],
];

#[Route('/admin/images-site', name: 'admin_site_images')]
public function siteImages(EntityManagerInterface $em): Response
{
    $images = $em->getRepository(SiteImage::class)->findBy(
        [],
        ['category' => 'ASC', 'sortOrder' => 'ASC', 'label' => 'ASC']
    );

    // Regroupement par catégorie
    $grouped = [];
    foreach ($images as $img) {
        $grouped[$img->getCategory()][] = $img;
    }

    // Réordonner selon l'ordre déclaré dans SITE_IMAGE_CATEGORIES
    $ordered = [];
    foreach (self::SITE_IMAGE_CATEGORIES as $key => $_) {
        if (isset($grouped[$key])) {
            $ordered[$key] = $grouped[$key];
            unset($grouped[$key]);
        }
    }
    // Catégories inconnues (fallback) placées en fin
    foreach ($grouped as $key => $items) {
        $ordered[$key] = $items;
    }

    return $this->render('admin/site_image/index.html.twig', [
        'images'     => $images,
        'grouped'    => $ordered,
        'categories' => self::SITE_IMAGE_CATEGORIES,
    ]);
}

#[Route('/admin/images-site/{id}/edit', name: 'admin_site_image_edit', requirements: ['id' => '\d+'])]
public function editSiteImage(
    Request $request,
    SiteImage $siteImage,
    EntityManagerInterface $em,
    SluggerInterface $slugger
): Response
{
    $form = $this->createForm(SiteImageType::class, $siteImage);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $imageFile = $form->get('image')->getData();

        if ($imageFile) {
            // Supprimer l'ancienne image si ce n'est pas un défaut
            if ($siteImage->getImage()) {
                $oldPath = $this->getParameter('images_directory') . '/' . $siteImage->getImage();
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = 'site-' . $siteImage->getSlug() . '-' . uniqid() . '.' . $imageFile->guessExtension();

            try {
                $imageFile->move($this->getParameter('images_directory'), $newFilename);
                $siteImage->setImage($newFilename);
                $em->flush();
                $this->addFlash('success', '✅ Image "' . $siteImage->getLabel() . '" mise à jour !');
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors du téléchargement de l\'image.');
            }
        }

        return $this->redirectToRoute('admin_site_images');
    }

    return $this->render('admin/site_image/edit.html.twig', [
        'form'      => $form->createView(),
        'siteImage' => $siteImage,
    ]);
}

#[Route('/admin/images-site/{id}/reset', name: 'admin_site_image_reset', methods: ['POST'], requirements: ['id' => '\d+'])]
public function resetSiteImage(SiteImage $siteImage, Request $request, EntityManagerInterface $em): Response
{
    if ($this->isCsrfTokenValid('reset_site_image_' . $siteImage->getId(), $request->request->get('_token'))) {
        // Supprimer le fichier uploadé et réinitialiser (retour à l'image par défaut)
        if ($siteImage->getImage()) {
            $oldPath = $this->getParameter('images_directory') . '/' . $siteImage->getImage();
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
            $siteImage->setImage(null);
            $em->flush();
        }
        $this->addFlash('success', 'Image réinitialisée (retour à la version par défaut).');
    }

    return $this->redirectToRoute('admin_site_images');
}

// ───────────────────────────────────────────────
// STATISTIQUES GLOBALES DU SITE
// ───────────────────────────────────────────────

#[Route('/admin/statistiques', name: 'admin_stats')]
public function stats(
    Request $request,
    CommandeRepository $commandeRepo,
    UserRepository $userRepo,
    ReviewRepository $reviewRepo,
    EntityManagerInterface $em
): Response
{
    // Filtres année + mois (mois optionnel — 0/null = toute l'année)
    $annee = (int) $request->query->get('annee', date('Y'));
    $mois  = $request->query->get('mois');
    $mois  = ($mois !== null && $mois !== '' && (int) $mois >= 1 && (int) $mois <= 12)
        ? (int) $mois
        : null;
    $anneeCourante = (int) date('Y');

    // Libellé de la période (pour titre des KPI)
    $moisNoms = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
    $periodeLabel = $mois !== null ? $moisNoms[$mois] . ' ' . $annee : 'Année ' . $annee;

    // ─── CA & Commandes (filtrés par année/mois) ───
    $ventesParMois       = $commandeRepo->getVentesParMois($annee); // graphique reste annuel
    $totalAnnee          = $commandeRepo->getTotalAnnee($annee, $mois);
    $comparison          = $commandeRepo->getComparisonPreviousYear($annee);
    $countByStatut       = $commandeRepo->getCountByStatut($annee, $mois);
    $ventesCategorie     = $commandeRepo->getVentesParCategorie($annee, $mois);
    $bestSellers         = $commandeRepo->getBestSellers(10, $annee, $mois);
    $totalArticlesVendus = $commandeRepo->getTotalArticlesVendus($annee, $mois);

    // Commandes de la période sélectionnée pour le panier moyen
    $conn = $em->getConnection();
    $whereCmd = 'YEAR(created_at) = :annee';
    $paramsCmd = ['annee' => $annee];
    if ($mois !== null) {
        $whereCmd .= ' AND MONTH(created_at) = :mois';
        $paramsCmd['mois'] = $mois;
    }
    $cmdRows = $conn->executeQuery(
        'SELECT total, statut FROM commande WHERE ' . $whereCmd,
        $paramsCmd
    )->fetchAllAssociative();
    $nbCommandes = count($cmdRows);
    $validCommandes = array_filter($cmdRows, fn($c) => $c['statut'] !== 'annulee');
    $panierMoyen = count($validCommandes) > 0
        ? array_sum(array_map(fn($c) => (float) $c['total'], $validCommandes)) / count($validCommandes)
        : 0;

    // ─── Clients ───
    $clientsParMois    = $userRepo->getNouveauxClientsParMois($annee); // graphique reste annuel
    $inscriptionsStats = $userRepo->getInscriptionsStats();
    $totalClients      = $inscriptionsStats['total'];

    // ─── Avis ───
    $avgReviewRating     = $reviewRepo->getAverageRating();
    $ratingDistribution  = $reviewRepo->getRatingDistribution();
    $nbReviewsTotal      = $reviewRepo->count([]);
    $nbReviewsPending    = $reviewRepo->count(['status' => Review::STATUS_PENDING]);
    $nbReviewsApproved   = $reviewRepo->count(['status' => Review::STATUS_APPROVED]);

    // ─── Années disponibles ───
    $anneesDisponibles = [];
    $rowsAnnees = $em->getConnection()->executeQuery(
        'SELECT DISTINCT YEAR(created_at) AS y FROM commande ORDER BY y DESC'
    )->fetchAllAssociative();
    foreach ($rowsAnnees as $r) {
        $anneesDisponibles[] = (int) $r['y'];
    }
    if (!in_array($anneeCourante, $anneesDisponibles, true)) {
        array_unshift($anneesDisponibles, $anneeCourante);
    }

    return $this->render('admin/stats/index.html.twig', [
        'annee'              => $annee,
        'mois'               => $mois,
        'moisNoms'           => $moisNoms,
        'periodeLabel'       => $periodeLabel,
        'anneeCourante'      => $anneeCourante,
        'anneesDisponibles'  => $anneesDisponibles,

        // Ventes
        'ventesParMois'      => $ventesParMois,
        'totalAnnee'         => $totalAnnee,
        'comparison'         => $comparison,
        'countByStatut'      => $countByStatut,
        'ventesCategorie'    => $ventesCategorie,
        'bestSellers'        => $bestSellers,
        'totalArticlesVendus'=> $totalArticlesVendus,
        'nbCommandes'        => $nbCommandes,
        'panierMoyen'        => $panierMoyen,

        // Clients
        'clientsParMois'     => $clientsParMois,
        'inscriptionsStats'  => $inscriptionsStats,
        'totalClients'       => $totalClients,

        // Avis
        'avgReviewRating'    => $avgReviewRating,
        'ratingDistribution' => $ratingDistribution,
        'nbReviewsTotal'     => $nbReviewsTotal,
        'nbReviewsPending'   => $nbReviewsPending,
        'nbReviewsApproved'  => $nbReviewsApproved,
    ]);
}

// ═══════════════════════════════════════════════════
//  CODES PROMO (gestion admin)
// ═══════════════════════════════════════════════════

#[Route('/admin/promos', name: 'admin_promos')]
public function promos(PromoCodeRepository $repo): Response
{
    $promos = $repo->findBy([], ['createdAt' => 'DESC']);

    return $this->render('admin/promo/index.html.twig', [
        'promos' => $promos,
    ]);
}

#[Route('/admin/promos/new', name: 'admin_promo_new', methods: ['GET', 'POST'])]
public function newPromo(Request $request, EntityManagerInterface $em): Response
{
    $promo = new PromoCode();
    $form  = $this->createForm(PromoCodeType::class, $promo);
    $form->handleRequest($request);

    if ($form->isSubmitted()) {
        if ($form->isValid()) {
            // Vérifier unicité du code
            $existing = $em->getRepository(PromoCode::class)->findByCode($promo->getCode());
            if ($existing) {
                $this->addFlash('error', 'Ce code existe déjà. Choisissez un autre identifiant.');
                return $this->render('admin/promo/new.html.twig', ['form' => $form->createView()]);
            }

            $em->persist($promo);
            $em->flush();

            $this->addFlash('success', sprintf('Code promo "%s" créé avec succès !', $promo->getCode()));
            return $this->redirectToRoute('admin_promos');
        }

        // Formulaire invalide : on expose toutes les erreurs en flash pour diagnostic
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $fieldName = $origin && $origin->getName() ? $origin->getName() : 'global';
            $this->addFlash('error', sprintf('[%s] %s', $fieldName, $error->getMessage()));
        }
    }

    return $this->render('admin/promo/new.html.twig', [
        'form' => $form->createView(),
    ]);
}

#[Route('/admin/promos/{id}/edit', name: 'admin_promo_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
public function editPromo(Request $request, PromoCode $promo, EntityManagerInterface $em): Response
{
    $originalCode = $promo->getCode();
    $form = $this->createForm(PromoCodeType::class, $promo);
    $form->handleRequest($request);

    if ($form->isSubmitted()) {
        if ($form->isValid()) {
            // Si le code a changé, vérifier l'unicité
            if ($promo->getCode() !== $originalCode) {
                $existing = $em->getRepository(PromoCode::class)->findByCode($promo->getCode());
                if ($existing && $existing->getId() !== $promo->getId()) {
                    $this->addFlash('error', 'Ce code existe déjà. Choisissez un autre identifiant.');
                    return $this->render('admin/promo/edit.html.twig', [
                        'form'  => $form->createView(),
                        'promo' => $promo,
                    ]);
                }
            }

            $em->flush();
            $this->addFlash('success', sprintf('Code promo "%s" modifié.', $promo->getCode()));
            return $this->redirectToRoute('admin_promos');
        }

        // Formulaire invalide : on expose toutes les erreurs en flash
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $fieldName = $origin && $origin->getName() ? $origin->getName() : 'global';
            $this->addFlash('error', sprintf('[%s] %s', $fieldName, $error->getMessage()));
        }
    }

    return $this->render('admin/promo/edit.html.twig', [
        'form'  => $form->createView(),
        'promo' => $promo,
    ]);
}

#[Route('/admin/promos/{id}/toggle', name: 'admin_promo_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
public function togglePromo(Request $request, PromoCode $promo, EntityManagerInterface $em): Response
{
    if (!$this->isCsrfTokenValid('toggle_promo_' . $promo->getId(), $request->request->get('_token'))) {
        $this->addFlash('error', 'Token CSRF invalide.');
        return $this->redirectToRoute('admin_promos');
    }

    $promo->setIsActive(!$promo->isActive());
    $em->flush();

    $this->addFlash(
        'success',
        sprintf(
            'Code "%s" %s.',
            $promo->getCode(),
            $promo->isActive() ? 'activé' : 'désactivé'
        )
    );

    return $this->redirectToRoute('admin_promos');
}

#[Route('/admin/promos/{id}/delete', name: 'admin_promo_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
public function deletePromo(Request $request, PromoCode $promo, EntityManagerInterface $em): Response
{
    if (!$this->isCsrfTokenValid('delete_promo_' . $promo->getId(), $request->request->get('_token'))) {
        $this->addFlash('error', 'Token CSRF invalide.');
        return $this->redirectToRoute('admin_promos');
    }

    $code = $promo->getCode();
    $em->remove($promo);
    $em->flush();

    $this->addFlash('success', sprintf('Code "%s" supprimé.', $code));
    return $this->redirectToRoute('admin_promos');
}

// ═══════════════════════════════════════════════════
//  PARAMÈTRES GLOBAUX DU SITE (AppSetting)
// ═══════════════════════════════════════════════════

/**
 * Libellés humains des catégories affichés dans l'UI admin.
 * `masterToggleKey` (optionnel) : si défini, ce setting devient le toggle principal
 * qui grise les autres champs de la catégorie quand il est OFF.
 */
private const SETTINGS_CATEGORIES = [
    'site' => [
        'label' => 'État du site & pages disponibles',
        'icon'  => 'fas fa-power-off',
        'description' => 'Contrôlez la disponibilité globale du site et de chaque page individuellement. Pratique pendant la préparation du catalogue : le client peut remplir le site tranquillement sans que les visiteurs n\'y accèdent encore.',
        // Pas de masterToggleKey : chaque setting est indépendant
    ],
    'review_reward' => [
        'label' => 'Récompense avis client',
        'icon'  => 'fas fa-gift',
        'description' => 'Offrez un code promo unique à chaque client qui laisse un avis — peu importe la note. Encourage les retours clients tout en restant conforme à la réglementation (Directive Omnibus UE).',
        'masterToggleKey' => 'review_reward_enabled',
    ],
    // D'autres catégories viendront s'ajouter ici (livraison gratuite, notifications…)
];

// ═══════════════════════════════════════════════════
//  RÉSEAUX SOCIAUX (gestion admin)
// ═══════════════════════════════════════════════════

#[Route('/admin/reseaux-sociaux', name: 'admin_social_links', methods: ['GET'])]
public function socialLinks(SocialLinkRepository $repo): Response
{
    $links = $repo->findBy([], ['sortOrder' => 'ASC', 'id' => 'ASC']);

    return $this->render('admin/social/index.html.twig', [
        'links' => $links,
    ]);
}

#[Route('/admin/reseaux-sociaux/new', name: 'admin_social_link_new', methods: ['GET', 'POST'])]
public function newSocialLink(Request $request, EntityManagerInterface $em): Response
{
    $link = new SocialLink();
    $form = $this->createForm(SocialLinkType::class, $link);
    $form->handleRequest($request);

    if ($form->isSubmitted()) {
        if ($form->isValid()) {
            $em->persist($link);
            $em->flush();
            $this->addFlash('success', sprintf('Lien "%s" ajouté.', $link->getDisplayLabel()));
            return $this->redirectToRoute('admin_social_links');
        }
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $name = $origin && $origin->getName() ? $origin->getName() : 'global';
            $this->addFlash('error', sprintf('[%s] %s', $name, $error->getMessage()));
        }
    }

    return $this->render('admin/social/new.html.twig', [
        'form' => $form->createView(),
    ]);
}

#[Route('/admin/reseaux-sociaux/{id}/edit', name: 'admin_social_link_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
public function editSocialLink(Request $request, SocialLink $link, EntityManagerInterface $em): Response
{
    $form = $this->createForm(SocialLinkType::class, $link);
    $form->handleRequest($request);

    if ($form->isSubmitted()) {
        if ($form->isValid()) {
            $em->flush();
            $this->addFlash('success', sprintf('Lien "%s" modifié.', $link->getDisplayLabel()));
            return $this->redirectToRoute('admin_social_links');
        }
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $name = $origin && $origin->getName() ? $origin->getName() : 'global';
            $this->addFlash('error', sprintf('[%s] %s', $name, $error->getMessage()));
        }
    }

    return $this->render('admin/social/edit.html.twig', [
        'form' => $form->createView(),
        'link' => $link,
    ]);
}

#[Route('/admin/reseaux-sociaux/{id}/toggle', name: 'admin_social_link_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
public function toggleSocialLink(Request $request, SocialLink $link, EntityManagerInterface $em): Response
{
    if (!$this->isCsrfTokenValid('toggle_social_' . $link->getId(), $request->request->get('_token'))) {
        $this->addFlash('error', 'Token CSRF invalide.');
        return $this->redirectToRoute('admin_social_links');
    }
    $link->setIsActive(!$link->isActive());
    $em->flush();
    $this->addFlash('success', sprintf(
        'Lien "%s" %s.',
        $link->getDisplayLabel(),
        $link->isActive() ? 'activé' : 'désactivé'
    ));
    return $this->redirectToRoute('admin_social_links');
}

#[Route('/admin/reseaux-sociaux/{id}/delete', name: 'admin_social_link_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
public function deleteSocialLink(Request $request, SocialLink $link, EntityManagerInterface $em): Response
{
    if (!$this->isCsrfTokenValid('delete_social_' . $link->getId(), $request->request->get('_token'))) {
        $this->addFlash('error', 'Token CSRF invalide.');
        return $this->redirectToRoute('admin_social_links');
    }
    $label = $link->getDisplayLabel();
    $em->remove($link);
    $em->flush();
    $this->addFlash('success', sprintf('Lien "%s" supprimé.', $label));
    return $this->redirectToRoute('admin_social_links');
}

#[Route('/admin/parametres', name: 'admin_settings', methods: ['GET', 'POST'])]
public function settings(
    Request $request,
    AppSettingRepository $repo,
    EntityManagerInterface $em,
    \App\Service\AppSettingService $settingService
): Response {
    $allSettings = $repo->findAllGrouped();

    // POST : sauvegarde des valeurs modifiées
    if ($request->isMethod('POST')) {
        if (!$this->isCsrfTokenValid('update_settings', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_settings');
        }

        $submitted = $request->request->all('settings') ?: [];

        foreach ($allSettings as $setting) {
            $key = $setting->getKey();

            // Cas spécial booléen : si la case est absente du POST, c'est "false"
            if ($setting->getType() === AppSetting::TYPE_BOOL) {
                $setting->setTypedValue(isset($submitted[$key]));
                continue;
            }

            if (!array_key_exists($key, $submitted)) {
                continue;
            }

            $rawValue = $submitted[$key];

            // Cast selon le type
            switch ($setting->getType()) {
                case AppSetting::TYPE_INT:
                    $setting->setTypedValue((int) $rawValue);
                    break;
                case AppSetting::TYPE_FLOAT:
                    $setting->setTypedValue(is_numeric($rawValue) ? (float) $rawValue : 0.0);
                    break;
                case AppSetting::TYPE_STRING:
                default:
                    $setting->setTypedValue((string) $rawValue);
                    break;
            }
        }

        $em->flush();
        $settingService->clearCache();

        $this->addFlash('success', '✅ Paramètres enregistrés.');
        return $this->redirectToRoute('admin_settings');
    }

    // GET : groupement par catégorie pour l'affichage
    $grouped = [];
    foreach ($allSettings as $setting) {
        $grouped[$setting->getCategory()][] = $setting;
    }

    return $this->render('admin/settings/index.html.twig', [
        'grouped'    => $grouped,
        'categories' => self::SETTINGS_CATEGORIES,
    ]);
}

}