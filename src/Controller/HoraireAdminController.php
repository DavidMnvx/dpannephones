<?php

namespace App\Controller;

use App\Form\HoraireType;
use App\Repository\HoraireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HoraireAdminController extends AbstractController
{
    #[Route('/admin/horaires', name: 'admin_horaires')]
    public function edit(Request $request, HoraireRepository $horaireRepository, EntityManagerInterface $entityManager): Response
    {
        $horaires = $horaireRepository->findAllOrderedByJour();

        $form = $this->createFormBuilder(['horaires' => $horaires])
            ->add('horaires', CollectionType::class, [
                'entry_type'    => HoraireType::class,
                'entry_options' => ['label' => false],
                'label'         => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer les horaires',
                'attr'  => ['class' => 'btn btn-primary mt-3'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Horaires mis à jour avec succès !');

            return $this->redirectToRoute('admin_horaires');
        }

        return $this->render('admin/horaires/edit.html.twig', [
            'form'     => $form->createView(),
            'horaires' => $horaires,
        ]);
    }
}
