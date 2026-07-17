<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Photo;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/admin/articles')]
class ArticleController extends AbstractController
{
    /**
     * Mapping centralisé : catégorie → [clé JSON specs => nom champ formulaire]
     * Source unique de vérité pour new() et edit().
     *
     * ⚠️ Si tu ajoutes un champ dans ArticleType, ajoute-le juste ici — tout le reste suit.
     */
    private const SPECS_MAPPING = [
        'pc_gamer' => [
            'processeur'      => 'gamer_cpu',
            'carte_graphique' => 'gamer_gpu',
            'ram'             => 'gamer_ram',
            'stockage'        => 'gamer_storage',
            'carte_mere'      => 'gamer_motherboard',
            'alimentation'    => 'gamer_psu',
            'boitier'         => 'gamer_case',
            'refroidissement' => 'gamer_cooling',
            'connectique'     => 'gamer_connectivity',
            'reseau'          => 'gamer_network',
            'os'              => 'gamer_os',
            'peripheriques'   => 'gamer_peripherals',
            'ecran_inclus'    => 'gamer_screen_included',
            'eclairage_rgb'   => 'gamer_rgb',
            'garantie'        => 'gamer_warranty',
        ],
        'pc_bureautique' => [
            'processeur'      => 'bureau_cpu',
            'carte_graphique' => 'bureau_gpu',
            'ram'             => 'bureau_ram',
            'stockage'        => 'bureau_storage',
            'carte_mere'      => 'bureau_motherboard',
            'connectique'     => 'bureau_connectivity',
            'reseau'          => 'bureau_network',
            'os'              => 'bureau_os',
            'ecran'           => 'bureau_screen',
            'peripheriques'   => 'bureau_peripherals',
            'garantie'        => 'bureau_warranty',
        ],
        'pc_portable' => [
            'processeur'      => 'portable_cpu',
            'carte_graphique' => 'portable_gpu',
            'ram'             => 'portable_ram',
            'stockage'        => 'portable_storage',
            'ecran'           => 'portable_screen',
            'type_ecran'      => 'portable_screen_type',
            'resolution'      => 'portable_resolution',
            'os'              => 'portable_os',
            'autonomie'       => 'portable_battery',
            'poids'           => 'portable_weight',
            'clavier'         => 'portable_keyboard',
            'webcam'          => 'portable_webcam',
            'connectique'     => 'portable_connectivity',
            'reseau'          => 'portable_network',
            'garantie'        => 'portable_warranty',
        ],
        'accessoires' => [
            'type'             => 'acc_type',
            'marque'           => 'acc_brand',
            'compatibilite'    => 'acc_compatibility',
            'materiau'         => 'acc_material',
            'couleur'          => 'acc_color',
            'connectique'      => 'acc_connector',
            'puissance'        => 'acc_power',
            'technologie'      => 'acc_technology',
            'nombre_ports'     => 'acc_ports_count',
            'longueur'         => 'acc_length',
            'certifications'   => 'acc_certifications',
            'autonomie'        => 'acc_battery_life',
            'resistance_eau'   => 'acc_water_resistance',
            'contenu_boite'    => 'acc_contents',
            'garantie'         => 'acc_warranty',
        ],
        'telephone' => [
            'brand'            => 'tel_brand',
            'model'            => 'tel_model',
            'os'               => 'tel_os',
            'year'             => 'tel_year',
            'screen_size'      => 'tel_screen_size',
            'screen_type'      => 'tel_screen_type',
            'resolution'       => 'tel_resolution',
            'processor'        => 'tel_processor',
            'ram'              => 'tel_ram',
            'storage_capacity' => 'tel_storage',
            'camera'           => 'tel_camera',
            'camera_front'     => 'tel_camera_front',
            'other_cameras'    => 'tel_other_cameras',
            'network'          => 'tel_network',
            'sim'              => 'tel_sim',
            'connectivity'     => 'tel_connectivity',
            'connector'        => 'tel_connector',
            'battery_capacity' => 'tel_battery_capacity',
            'battery_health'   => 'tel_battery',
            'weight'           => 'tel_weight',
            'dimensions'       => 'tel_dimensions',
            'sensors'          => 'tel_sensors',
            'sar'              => 'tel_sar',
            'note'             => 'tel_note',
            'color'            => 'tel_color',
        ],
    ];

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
            $this->handleArticlePhotos($form, $article, $request, $slugger);
            $this->extractSpecsFromForm($form, $article);

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

        // Pré-remplir les champs specs depuis les données stockées (avant handleRequest)
        if (!$request->isMethod('POST')) {
            $this->populateSpecsIntoForm($form, $article);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleArticlePhotos($form, $article, $request, $slugger);
            $this->extractSpecsFromForm($form, $article);

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

    #[Route('/photo/{id}/delete', name: 'admin_article_photo_delete', methods: ['POST'])]
    public function deletePhoto(Photo $photo, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-photo-' . $photo->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $articleId = $photo->getArticle()?->getId();
        $em->remove($photo);
        $em->flush();
        $this->addFlash('success', 'Photo supprimée.');

        return $articleId
            ? $this->redirectToRoute('admin_article_edit', ['id' => $articleId])
            : $this->redirectToRoute('admin_article_index');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Helpers privés
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Gère l'ensemble des changements sur les photos d'un article :
     *  - suppression des photos existantes marquées (deleted_photo_ids)
     *  - upload des nouveaux fichiers (mapped:false 'photos')
     *  - désignation de la photo principale (article.image = la principale, article.photos = le reste)
     *
     * Inputs POST attendus (hidden côté template) :
     *  - main_photo_source     : 'existing' | 'new' | 'unchanged'
     *  - main_photo_id         : id de la Photo choisie, ou 'main' si l'image principale actuelle reste principale
     *  - main_photo_new_index  : index (dans l'ordre d'upload) du nouveau fichier choisi comme principal
     *  - deleted_photo_ids     : JSON array d'ids de Photo à supprimer (peut contenir 'main' pour supprimer l'image principale)
     */
    private function handleArticlePhotos($form, Article $article, Request $request, SluggerInterface $slugger): void
    {
        // 1) Suppressions demandées sur les photos existantes
        $deletedIds = json_decode((string) $request->request->get('deleted_photo_ids', '[]'), true);
        $deletedIds = is_array($deletedIds) ? $deletedIds : [];

        if (in_array('main', $deletedIds, true)) {
            $article->setImage(null);
        }

        foreach ($article->getPhotos()->toArray() as $existing) {
            if (in_array((string) $existing->getId(), array_map('strval', $deletedIds), true)) {
                $article->removePhoto($existing);
            }
        }

        // 2) Upload des nouveaux fichiers → filenames dans l'ordre de sélection
        $files = $form->get('photos')->getData() ?? [];
        $newFilenames = [];
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $fn = $this->uploadImageFile($file, $slugger);
            if ($fn === null) {
                $this->addFlash('warning', 'Une photo n\'a pas pu être enregistrée.');
                continue;
            }
            $newFilenames[] = $fn;
        }

        // 3) Détermination du nouveau filename principal
        $source       = $request->request->get('main_photo_source', 'unchanged');
        $mainId       = $request->request->get('main_photo_id', '');
        $mainNewIndex = $request->request->has('main_photo_new_index') && $request->request->get('main_photo_new_index') !== ''
            ? (int) $request->request->get('main_photo_new_index')
            : -1;

        $newMainFilename    = null;
        $newMainFromPhotoId = null;

        if ($source === 'new' && $mainNewIndex >= 0 && isset($newFilenames[$mainNewIndex])) {
            $newMainFilename = $newFilenames[$mainNewIndex];
        } elseif ($source === 'existing') {
            if ($mainId === 'main') {
                $newMainFilename = $article->getImage(); // inchangé
            } else {
                foreach ($article->getPhotos() as $p) {
                    if ((string) $p->getId() === (string) $mainId) {
                        $newMainFilename    = $p->getFilename();
                        $newMainFromPhotoId = $p->getId();
                        break;
                    }
                }
            }
        }

        // 4) Application : article.image devient la principale ; l'ancienne principale (si différente) part en Photo
        if ($newMainFilename !== null && $newMainFilename !== $article->getImage()) {
            $oldMain = $article->getImage();
            if ($oldMain) {
                $article->addPhoto(
                    (new Photo())->setFilename($oldMain)->setPosition($article->getPhotos()->count())
                );
            }
            if ($newMainFromPhotoId !== null) {
                foreach ($article->getPhotos()->toArray() as $p) {
                    if ($p->getId() === $newMainFromPhotoId) {
                        $article->removePhoto($p);
                        break;
                    }
                }
            }
            $article->setImage($newMainFilename);
        }

        // 5) Ajout des autres nouveaux uploads en Photo (celui devenu principal est déjà exclu ci-dessus)
        foreach ($newFilenames as $i => $fn) {
            if ($source === 'new' && $i === $mainNewIndex) {
                continue;
            }
            $article->addPhoto(
                (new Photo())->setFilename($fn)->setPosition($article->getPhotos()->count())
            );
        }

        // 6) Fallback : article.image nul mais des photos existent → première photo devient principale
        if ($article->getImage() === null && $article->getPhotos()->count() > 0) {
            $first = $article->getPhotos()->first();
            $article->setImage($first->getFilename());
            $article->removePhoto($first);
        }
    }

    private function uploadImageFile(UploadedFile $file, SluggerInterface $slugger): ?string
    {
        $safeFilename = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $newFilename  = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($this->getParameter('images_directory'), $newFilename);
            return $newFilename;
        } catch (FileException) {
            return null;
        }
    }

    /**
     * Construit le JSON specs à partir des données du formulaire.
     *
     * On préserve les clés "custom" déjà présentes sur l'article qui ne sont PAS dans le mapping
     * (utile pour ne pas perdre d'anciennes données techniques non couvertes par le form standard,
     * ex. "certification", "longueur", "rotation"... ajoutées manuellement via seed/SQL).
     */
    private function extractSpecsFromForm($form, Article $article): void
    {
        $categorie = $form->get('categorie')->getData();
        $mapping   = self::SPECS_MAPPING[$categorie] ?? [];

        // 1. On part des specs existantes (pour préserver les clés custom hors mapping)
        $existingSpecs = $article->getSpecs() ?? [];
        $preservedCustom = array_diff_key($existingSpecs, array_flip(array_keys($mapping)));

        // 2. On écrase avec les nouvelles valeurs du formulaire
        $specs = [];
        foreach ($mapping as $specKey => $fieldName) {
            $val = $form->get($fieldName)->getData();
            if ($val !== null && $val !== '') {
                $specs[$specKey] = $val;
            }
        }

        // 3. Cas spécial : hydrogel — liste de modèles compatibles (textarea multi-lignes)
        if ($categorie === 'film_hydrogel') {
            $raw = $form->get('hydrogel_models')->getData();
            if ($raw) {
                $specs['compatible_models'] = array_values(array_filter(array_map('trim', explode("\n", $raw))));
            }
        }

        // 4. On fusionne : nouvelles valeurs du form + anciennes clés custom non couvertes
        $merged = array_merge($preservedCustom, $specs);

        $article->setSpecs($merged ?: null);
    }

    /**
     * Pré-remplit les champs du formulaire avec les specs déjà stockées.
     */
    private function populateSpecsIntoForm($form, Article $article): void
    {
        $specs = $article->getSpecs() ?? [];
        if (empty($specs)) {
            return;
        }

        // Parcourir tous les mappings — les champs qui n'existent pas dans la catégorie courante sont juste ignorés
        foreach (self::SPECS_MAPPING as $mapping) {
            foreach ($mapping as $specKey => $fieldName) {
                if (isset($specs[$specKey]) && $form->has($fieldName)) {
                    $form->get($fieldName)->setData($specs[$specKey]);
                }
            }
        }

        // Cas spécial : hydrogel
        if (isset($specs['compatible_models']) && is_array($specs['compatible_models']) && $form->has('hydrogel_models')) {
            $form->get('hydrogel_models')->setData(implode("\n", $specs['compatible_models']));
        }
    }
}
