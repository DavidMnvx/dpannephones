<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Photo;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
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
    public function __construct(private CategoryRepository $categoryRepo)
    {
    }

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
            'categoriesMap' => $this->categoryRepo->getSlugLabelMap(),
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
            $this->handleArticlePhotos($form, $article, $request, $slugger, $em);
            $this->extractSpecsFromForm($form, $article);

            $em->persist($article);
            $em->flush();
            $this->addFlash('success', 'Article ajouté avec succès !');

            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/new.html.twig', [
            'categoryTemplates' => $this->categoryRepo->getSlugSpecsTemplateMap(),
            'form'    => $form->createView(),
            'article' => $article,
            'colors'         => $em->getRepository(\App\Entity\ProductColor::class)->findAllOrdered(),
            'popularPhones'  => $em->getRepository(\App\Entity\PopularPhoneModel::class)->findGroupedByBrandAndFamille(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_article_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Article $article, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ArticleType::class, $article);

        // Pré-remplir les champs specs depuis les données stockées (avant handleRequest)
        if (!$request->isMethod('POST')) {
            $this->populateSpecsIntoForm($form, $article, $em);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleArticlePhotos($form, $article, $request, $slugger, $em);
            $this->extractSpecsFromForm($form, $article);

            $em->flush();
            $this->addFlash('success', 'Article modifié avec succès !');

            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin/article/edit.html.twig', [
            'categoryTemplates' => $this->categoryRepo->getSlugSpecsTemplateMap(),
            'form'    => $form->createView(),
            'article' => $article,
            'colors'         => $em->getRepository(\App\Entity\ProductColor::class)->findAllOrdered(),
            'popularPhones'  => $em->getRepository(\App\Entity\PopularPhoneModel::class)->findGroupedByBrandAndFamille(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_article_delete', methods: ['POST', 'GET'])]
    public function delete(Article $article, EntityManagerInterface $em): Response
    {
        // L'article peut être référencé par des paniers en cours et des avis :
        // sans ce nettoyage, la contrainte SQL bloque la suppression (erreur 500).
        $em->createQuery('DELETE FROM App\Entity\CartItem ci WHERE ci.article = :a')
           ->setParameter('a', $article)->execute();
        $em->createQuery('UPDATE App\Entity\Review r SET r.article = NULL WHERE r.article = :a')
           ->setParameter('a', $article)->execute();
        // Les commandes passées gardent leur trace : commande_item.article passe à NULL
        // automatiquement (SET NULL) et le nom snapshot reste.

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
    private function handleArticlePhotos($form, Article $article, Request $request, SluggerInterface $slugger, EntityManagerInterface $em): void
    {
        // 0) Récupération de la map des couleurs {"main": id, "42": id, "new:0": id, ...}
        $photoColors = json_decode((string) $request->request->get('photo_colors', '{}'), true);
        $photoColors = is_array($photoColors) ? $photoColors : [];
        $colorRepo   = $em->getRepository(\App\Entity\ProductColor::class);
        $resolveColor = function ($colorId) use ($colorRepo) {
            if (!$colorId) return null;
            return $colorRepo->find((int) $colorId);
        };

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
        $newPhotosByIndex = [];
        foreach ($newFilenames as $i => $fn) {
            if ($source === 'new' && $i === $mainNewIndex) {
                continue;
            }
            $photo = (new Photo())->setFilename($fn)->setPosition($article->getPhotos()->count());
            $article->addPhoto($photo);
            $newPhotosByIndex[$i] = $photo;
        }

        // 6) Fallback : article.image nul mais des photos existent → première photo devient principale
        if ($article->getImage() === null && $article->getPhotos()->count() > 0) {
            $first = $article->getPhotos()->first();
            $article->setImage($first->getFilename());
            $article->removePhoto($first);
            unset($newPhotosByIndex[array_search($first, $newPhotosByIndex, true)]);
        }

        // 7) Application des couleurs aux photos
        //    - photos existantes non supprimées : setColor() depuis $photoColors[photo.id]
        //    - nouveaux uploads : setColor() depuis $photoColors["new:i"]
        //    - main photo : couleur stockée dans specs.main_photo_color_id
        foreach ($article->getPhotos() as $photo) {
            $key = (string) $photo->getId();
            $photo->setColor(
                isset($photoColors[$key]) ? $resolveColor($photoColors[$key]) : null
            );
        }
        foreach ($newPhotosByIndex as $i => $photo) {
            $key = 'new:' . $i;
            if (isset($photoColors[$key])) {
                $photo->setColor($resolveColor($photoColors[$key]));
            }
        }

        // Couleur de la photo principale (stockée dans specs)
        $existingSpecs = $article->getSpecs() ?? [];
        if (isset($photoColors['main']) && $photoColors['main']) {
            $existingSpecs['main_photo_color_id'] = (int) $photoColors['main'];
        } else {
            unset($existingSpecs['main_photo_color_id']);
        }
        $article->setSpecs($existingSpecs ?: null);

        // 8) Photo principale PAR coloris (étoile de chaque rangée)
        //    color_main_photos : {"<colorId>|generic": "<photoId>" | "new:<i>"}
        $colorMains = json_decode((string) $request->request->get('color_main_photos', '{}'), true);
        $colorMains = is_array($colorMains) ? $colorMains : [];

        $wanted = [];
        foreach ($colorMains as $ref) {
            $wanted[(string) $ref] = true;
        }

        foreach ($article->getPhotos() as $photo) {
            $photo->setIsMain(isset($wanted[(string) $photo->getId()]));
        }
        foreach ($newPhotosByIndex as $i => $photo) {
            if (isset($wanted['new:' . $i])) {
                $photo->setIsMain(true);
            }
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
        // Le gabarit de champs techniques est défini par la catégorie (admin > Catégories)
        $template = $this->categoryRepo->findOneBy(['slug' => (string) $categorie])?->getSpecsTemplate();
        $mapping  = self::SPECS_MAPPING[$template ?? '']
            ?? self::SPECS_MAPPING[$categorie]
            ?? self::SPECS_MAPPING[str_replace('_occasion', '', (string) $categorie)]
            ?? [];

        // 1. On part des specs existantes (pour préserver les clés custom hors mapping)
        $existingSpecs = $article->getSpecs() ?? [];
        $preservedCustom = array_diff_key($existingSpecs, array_flip(array_keys($mapping)));

        // 2. On écrase avec les nouvelles valeurs du formulaire
        $specs = [];
        foreach ($mapping as $specKey => $fieldName) {
            $val = $form->get($fieldName)->getData();
            // Cas EntityType (couleur) → on ne stocke que le nom dans les specs
            if ($val instanceof \App\Entity\ProductColor) {
                $val = $val->getName();
            }
            if ($val !== null && $val !== '') {
                $specs[$specKey] = $val;
            }
        }

        // 3. Cas spécial : hydrogel et coques — modèles compatibles groupés par marque
        //    Format admin attendu : une ligne par marque, sous la forme
        //      "Marque: modèle1, modèle2, modèle3"
        //    Ex : "Apple: iPhone 12, iPhone 13, iPhone 14 Pro"
        //    Stocké dans specs.brand_models = { "Apple": ["iPhone 12", …], … }
        if (in_array($categorie, ['film_hydrogel', 'coque'], true)) {
            $sourceField = $categorie === 'coque' ? 'coque_models' : 'hydrogel_models';
            $raw = $form->has($sourceField)
                ? (string) ($form->get($sourceField)->getData() ?? '')
                : '';
            $brandModels = [];
            foreach (preg_split('/\r?\n/', $raw) as $line) {
                $line = trim($line);
                if ($line === '' || !str_contains($line, ':')) {
                    continue;
                }
                [$brand, $modelsStr] = explode(':', $line, 2);
                $brand  = trim($brand);
                $models = array_values(array_filter(array_map('trim', explode(',', $modelsStr))));
                if ($brand !== '' && !empty($models)) {
                    $brandModels[$brand] = $models;
                }
            }
            if (!empty($brandModels)) {
                $specs['brand_models'] = $brandModels;
            }
            // Compat descendante : on retire l'ancien format s'il traîne
            unset($specs['compatible_models']);
        }

        // 4. Saisies faites sous une autre catégorie : on les récupère aussi, pour ne
        //    rien perdre quand l'admin change de catégorie en cours de route. Les clés
        //    du gabarit courant restent sous l'autorité exclusive de ses propres champs
        //    (sinon vider un champ ne le supprimerait plus) — sauf à la création, où
        //    rien n'a pu être vidé.
        $isCreation = $article->getId() === null;
        foreach (self::SPECS_MAPPING as $otherMapping) {
            foreach ($otherMapping as $specKey => $fieldName) {
                if (isset($specs[$specKey]) || !$form->has($fieldName)) {
                    continue;
                }
                if (!$isCreation && array_key_exists($specKey, $mapping)) {
                    continue;
                }
                $val = $form->get($fieldName)->getData();
                if ($val instanceof \App\Entity\ProductColor) {
                    $val = $val->getName();
                }
                if ($val !== null && $val !== '') {
                    $specs[$specKey] = $val;
                }
            }
        }

        // 5. On fusionne : nouvelles valeurs du form + anciennes clés custom non couvertes
        $merged = array_merge($preservedCustom, $specs);

        $article->setSpecs($merged ?: null);
    }

    /**
     * Pré-remplit les champs du formulaire avec les specs déjà stockées.
     */
    private function populateSpecsIntoForm($form, Article $article, EntityManagerInterface $em): void
    {
        $specs = $article->getSpecs() ?? [];
        if (empty($specs)) {
            return;
        }

        // Parcourir tous les mappings — les champs qui n'existent pas dans la catégorie courante sont juste ignorés
        foreach (self::SPECS_MAPPING as $mapping) {
            foreach ($mapping as $specKey => $fieldName) {
                if (isset($specs[$specKey]) && $form->has($fieldName)) {
                    $value = $specs[$specKey];
                    // Cas spécial : acc_color est un EntityType → on cherche la ProductColor par son nom
                    if ($fieldName === 'acc_color' && is_string($value)) {
                        $value = $em->getRepository(\App\Entity\ProductColor::class)
                            ->findOneBy(['name' => $value]);
                    }
                    if ($value !== null) {
                        $form->get($fieldName)->setData($value);
                    }
                }
            }
        }

        // Cas spécial : hydrogel & coque — reconstruire le format "Marque: modèle1, modèle2" par ligne
        $currentCategorie = $article->getCategorie();
        $targetField = $currentCategorie === 'coque' ? 'coque_models' : 'hydrogel_models';
        if ($form->has($targetField)) {
            $lines = [];
            if (isset($specs['brand_models']) && is_array($specs['brand_models'])) {
                foreach ($specs['brand_models'] as $brand => $models) {
                    if (is_array($models) && !empty($models)) {
                        $lines[] = $brand . ': ' . implode(', ', $models);
                    }
                }
            } elseif (isset($specs['compatible_models']) && is_array($specs['compatible_models'])) {
                // Ancien format à convertir : on met tout sous "Autres"
                $lines[] = 'Autres : ' . implode(', ', $specs['compatible_models']);
            }
            if (!empty($lines)) {
                $form->get($targetField)->setData(implode("\n", $lines));
            }
        }
    }
}
