<?php
namespace App\Controller;

use App\Entity\Article;
use App\Entity\Commande;
use App\Entity\Marque;
use App\Repository\CommandeRepository;
use App\Entity\Model;
use App\Entity\Reparation;
use App\Form\MarqueType;
use App\Form\ModelType;
use App\Form\ReparationType;
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
            $entityManager->persist($reparation);
            $entityManager->flush();
            $this->addFlash('success', 'Réparation ajoutée avec succès!');

            return $this->redirectToRoute('admin_index');
        }

        $marques = $entityManager->getRepository(Marque::class)->findAll();
        $models = $entityManager->getRepository(Model::class)->findAll();
        $reparations = $entityManager->getRepository(Reparation::class)->findAll();
        $articles = $entityManager->getRepository(Article::class)->findAll();

        $annee = (int) date('Y');
        $ventesParMois = $commandeRepo->getVentesParMois($annee);
        $totalAnnee    = $commandeRepo->getTotalAnnee($annee);
        $commandes     = $entityManager->getRepository(Commande::class)->findBy([], ['createdAt' => 'DESC'], 10);

        return $this->render('admin/index.html.twig', [
            'marqueForm' => $marqueForm->createView(),
            'modelForm' => $modelForm->createView(),
            'reparationForm' => $reparationForm->createView(),
            'marques' => $marques,
            'models' => $models,
            'reparations' => $reparations,
            'articles' => $articles,
            'ventesParMois' => $ventesParMois,
            'totalAnnee'    => $totalAnnee,
            'commandes'     => $commandes,
            'annee'         => $annee,
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


}