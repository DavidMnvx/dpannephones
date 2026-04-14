<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/admin/articles')]
class ArticleController extends AbstractController
{
    #[Route('/', name: 'admin_article_index', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository): Response
    {
        return $this->render('admin/article/index.html.twig', [
            'articles' => $articleRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFilename')->getData();

            if ($imageFile) {
                $safeFilename = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('images_directory'), $newFilename);
                    $article->setImage($newFilename);
                } catch (FileException $e) {}
            }

            // Assemble specs from form data
            $categorie = $form->get('categorie')->getData();
            $specs = [];

            if ($categorie === 'pc_gamer') {
                $map = [
                    'cpu'         => 'gamer_cpu',
                    'gpu'         => 'gamer_gpu',
                    'ram'         => 'gamer_ram',
                    'storage'     => 'gamer_storage',
                    'motherboard' => 'gamer_motherboard',
                    'psu'         => 'gamer_psu',
                    'case'        => 'gamer_case',
                    'cooling'     => 'gamer_cooling',
                    'os'          => 'gamer_os',
                ];
                foreach ($map as $specKey => $fieldName) {
                    $val = $form->get($fieldName)->getData();
                    if ($val) {
                        $specs[$specKey] = $val;
                    }
                }
            } elseif ($categorie === 'pc_bureautique') {
                $map = [
                    'cpu'         => 'bureau_cpu',
                    'ram'         => 'bureau_ram',
                    'storage'     => 'bureau_storage',
                    'os'          => 'bureau_os',
                    'screen_size' => 'bureau_screen',
                ];
                foreach ($map as $specKey => $fieldName) {
                    $val = $form->get($fieldName)->getData();
                    if ($val) {
                        $specs[$specKey] = $val;
                    }
                }
            } elseif ($categorie === 'pc_portable') {
                $map = [
                    'cpu'         => 'portable_cpu',
                    'gpu'         => 'portable_gpu',
                    'ram'         => 'portable_ram',
                    'storage'     => 'portable_storage',
                    'os'          => 'portable_os',
                    'screen_size' => 'portable_screen',
                    'battery'     => 'portable_battery',
                ];
                foreach ($map as $specKey => $fieldName) {
                    $val = $form->get($fieldName)->getData();
                    if ($val) {
                        $specs[$specKey] = $val;
                    }
                }
            } elseif ($categorie === 'film_hydrogel') {
                $raw = $form->get('hydrogel_models')->getData();
                if ($raw) {
                    $specs['compatible_models'] = array_values(array_filter(array_map('trim', explode("\n", $raw))));
                }
            } elseif ($categorie === 'telephone') {
                $map = [
                    'storage_capacity' => 'tel_storage',
                    'color'            => 'tel_color',
                    'network'          => 'tel_network',
                    'battery_health'   => 'tel_battery',
                ];
                foreach ($map as $specKey => $fieldName) {
                    $val = $form->get($fieldName)->getData();
                    if ($val) {
                        $specs[$specKey] = $val;
                    }
                }
            }

            $article->setSpecs($specs ?: null);

            $em->persist($article);
            $em->flush();
            $this->addFlash('success', 'Article ajouté avec succès !');

            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/new.html.twig', [
            'form'    => $form->createView(),
            'article' => $article,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_article_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Article $article, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ArticleType::class, $article);

        // Pre-populate spec fields from saved specs (before handleRequest)
        $specs = $article->getSpecs() ?? [];
        if (!$request->isMethod('POST')) {
            // PC Gamer
            $gamerMap = [
                'gamer_cpu'         => 'cpu',
                'gamer_gpu'         => 'gpu',
                'gamer_ram'         => 'ram',
                'gamer_storage'     => 'storage',
                'gamer_motherboard' => 'motherboard',
                'gamer_psu'         => 'psu',
                'gamer_case'        => 'case',
                'gamer_cooling'     => 'cooling',
                'gamer_os'          => 'os',
            ];
            foreach ($gamerMap as $fieldName => $specKey) {
                if (isset($specs[$specKey])) {
                    $form->get($fieldName)->setData($specs[$specKey]);
                }
            }
            // PC Bureautique
            $bureauMap = [
                'bureau_cpu'     => 'cpu',
                'bureau_ram'     => 'ram',
                'bureau_storage' => 'storage',
                'bureau_os'      => 'os',
                'bureau_screen'  => 'screen_size',
            ];
            foreach ($bureauMap as $fieldName => $specKey) {
                if (isset($specs[$specKey])) {
                    $form->get($fieldName)->setData($specs[$specKey]);
                }
            }
            // PC Portable
            $portableMap = [
                'portable_cpu'     => 'cpu',
                'portable_gpu'     => 'gpu',
                'portable_ram'     => 'ram',
                'portable_storage' => 'storage',
                'portable_os'      => 'os',
                'portable_screen'  => 'screen_size',
                'portable_battery' => 'battery',
            ];
            foreach ($portableMap as $fieldName => $specKey) {
                if (isset($specs[$specKey])) {
                    $form->get($fieldName)->setData($specs[$specKey]);
                }
            }
            // Hydrogel
            if (isset($specs['compatible_models']) && is_array($specs['compatible_models'])) {
                $form->get('hydrogel_models')->setData(implode("\n", $specs['compatible_models']));
            }
            // Téléphone
            $telMap = [
                'tel_storage' => 'storage_capacity',
                'tel_color'   => 'color',
                'tel_network' => 'network',
                'tel_battery' => 'battery_health',
            ];
            foreach ($telMap as $fieldName => $specKey) {
                if (isset($specs[$specKey])) {
                    $form->get($fieldName)->setData($specs[$specKey]);
                }
            }
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFilename')->getData();

            if ($imageFile) {
                $safeFilename = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move($this->getParameter('images_directory'), $newFilename);
                    $article->setImage($newFilename);
                } catch (FileException $e) {}
            }

            // Assemble specs from form data
            $categorie = $form->get('categorie')->getData();
            $specs = [];

            if ($categorie === 'pc_gamer') {
                $map = [
                    'cpu'         => 'gamer_cpu',
                    'gpu'         => 'gamer_gpu',
                    'ram'         => 'gamer_ram',
                    'storage'     => 'gamer_storage',
                    'motherboard' => 'gamer_motherboard',
                    'psu'         => 'gamer_psu',
                    'case'        => 'gamer_case',
                    'cooling'     => 'gamer_cooling',
                    'os'          => 'gamer_os',
                ];
                foreach ($map as $specKey => $fieldName) {
                    $val = $form->get($fieldName)->getData();
                    if ($val) {
                        $specs[$specKey] = $val;
                    }
                }
            } elseif ($categorie === 'pc_bureautique') {
                $map = [
                    'cpu'         => 'bureau_cpu',
                    'ram'         => 'bureau_ram',
                    'storage'     => 'bureau_storage',
                    'os'          => 'bureau_os',
                    'screen_size' => 'bureau_screen',
                ];
                foreach ($map as $specKey => $fieldName) {
                    $val = $form->get($fieldName)->getData();
                    if ($val) {
                        $specs[$specKey] = $val;
                    }
                }
            } elseif ($categorie === 'pc_portable') {
                $map = [
                    'cpu'         => 'portable_cpu',
                    'gpu'         => 'portable_gpu',
                    'ram'         => 'portable_ram',
                    'storage'     => 'portable_storage',
                    'os'          => 'portable_os',
                    'screen_size' => 'portable_screen',
                    'battery'     => 'portable_battery',
                ];
                foreach ($map as $specKey => $fieldName) {
                    $val = $form->get($fieldName)->getData();
                    if ($val) {
                        $specs[$specKey] = $val;
                    }
                }
            } elseif ($categorie === 'film_hydrogel') {
                $raw = $form->get('hydrogel_models')->getData();
                if ($raw) {
                    $specs['compatible_models'] = array_values(array_filter(array_map('trim', explode("\n", $raw))));
                }
            } elseif ($categorie === 'telephone') {
                $map = [
                    'storage_capacity' => 'tel_storage',
                    'color'            => 'tel_color',
                    'network'          => 'tel_network',
                    'battery_health'   => 'tel_battery',
                ];
                foreach ($map as $specKey => $fieldName) {
                    $val = $form->get($fieldName)->getData();
                    if ($val) {
                        $specs[$specKey] = $val;
                    }
                }
            }

            $article->setSpecs($specs ?: null);

            $em->flush();
            $this->addFlash('success', 'Article modifié avec succès !');

            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/edit.html.twig', [
            'form'    => $form->createView(),
            'article' => $article,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_article_delete', methods: ['POST', 'GET'])]
    public function delete(Article $article, EntityManagerInterface $em): Response
    {
        $em->remove($article);
        $em->flush();
        $this->addFlash('success', 'Article supprimé.');

        return $this->redirectToRoute('admin_article_index');
    }
}
