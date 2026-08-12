<?php

namespace App\Command;

use App\Entity\SiteImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seed des SiteImage modifiables depuis l'admin.
 *
 * Idempotente : crée les slugs manquants, met à jour label/description/defaultImage
 * sur les existants, ne touche JAMAIS à `image` (upload admin custom).
 *
 * Usage :
 *   php bin/console app:seed-site-images
 */
#[AsCommand(
    name: 'app:seed-site-images',
    description: 'Crée/met à jour la liste des images de site modifiables via l\'admin'
)]
class SeedSiteImagesCommand extends Command
{
    /**
     * Chaque entrée : category + sortOrder pour grouper l'affichage admin.
     * Catégories utilisées : 'home', 'pages_principales', 'ordinateur', 'legal', 'utilitaires'.
     */
    private const SEEDS = [
        // ═══════════════════ HOME ═══════════════════
        [
            'slug' => 'home_shop_photo',
            'label' => 'Accueil — Photo de la boutique',
            'description' => 'Photo de la devanture affichée dans la section "Notre Boutique" sur la page d\'accueil. Format paysage recommandé (min. 1200×800).',
            'default' => 'images/accueil/boutique_accueil2.jpeg',
            'category' => 'home', 'sortOrder' => 10,
        ],
        [
            'slug' => 'banner_home_carousel_1',
            'label' => 'Accueil — Carrousel slide 1 (Réparation)',
            'description' => 'Première image du carrousel en haut de la page d\'accueil. Format paysage large (min. 1920×700).',
            'default' => 'images/carousel/carousel1.jpg',
            'category' => 'home', 'sortOrder' => 20,
        ],
        [
            'slug' => 'banner_home_carousel_2',
            'label' => 'Accueil — Carrousel slide 2 (Reconditionnés)',
            'description' => 'Deuxième image du carrousel. Format paysage large (min. 1920×700).',
            'default' => 'images/carousel/carousel2.jpeg',
            'category' => 'home', 'sortOrder' => 30,
        ],
        [
            'slug' => 'banner_home_carousel_3',
            'label' => 'Accueil — Carrousel slide 3 (Accessoires)',
            'description' => 'Troisième image du carrousel. Format paysage large (min. 1920×700).',
            'default' => 'images/carousel/carousel3.jpeg',
            'category' => 'home', 'sortOrder' => 40,
        ],
        [
            'slug' => 'banner_home_carousel_4',
            'label' => 'Accueil — Carrousel slide 4 (PC)',
            'description' => 'Quatrième image du carrousel. Format paysage large (min. 1920×700).',
            'default' => 'images/carousel/carousel4.jpeg',
            'category' => 'home', 'sortOrder' => 50,
        ],

        // ═══════════════════ PAGES PRINCIPALES ═══════════════════
        [
            'slug' => 'banner_boutique',
            'label' => 'Bannière — Boutique',
            'description' => 'Image en haut de la page /boutique (et fiche produit). Format paysage (min. 1600×400).',
            'default' => 'images/baner/Boutique.png',
            'category' => 'pages_principales', 'sortOrder' => 10,
        ],
        [
            'slug' => 'banner_blog',
            'label' => 'Bannière — Blog',
            'description' => 'Image en haut de la page /blog (Conseils & Actualités). Format paysage (min. 1600×400).',
            'default' => 'images/baner/Boutique.png',
            'category' => 'pages_principales', 'sortOrder' => 15,
        ],
        [
            'slug' => 'banner_reparations',
            'label' => 'Bannière — Réparations',
            'description' => 'Image en haut des pages de réparation (PC, téléphone, tablette). Format paysage (min. 1600×400).',
            'default' => 'images/baner/quiSommesNous.webp',
            'category' => 'pages_principales', 'sortOrder' => 20,
        ],
        [
            'slug' => 'banner_avis',
            'label' => 'Bannière — Avis clients',
            'description' => 'Image en haut de la page /reviews. Format paysage (min. 1600×400).',
            'default' => 'images/baner/Boutique.png',
            'category' => 'pages_principales', 'sortOrder' => 30,
        ],
        [
            'slug' => 'banner_partenaires',
            'label' => 'Bannière — Partenaires',
            'description' => 'Image en haut de la page /partenaires. Format paysage (min. 1600×400).',
            'default' => 'images/baner/partenaires.svg',
            'category' => 'pages_principales', 'sortOrder' => 40,
        ],
        [
            'slug' => 'banner_contact',
            'label' => 'Bannière — Contact',
            'description' => 'Image en haut de la page /contact. Format paysage (min. 1600×400).',
            'default' => 'images/baner/contact.avif',
            'category' => 'pages_principales', 'sortOrder' => 50,
        ],

        // ═══════════════════ PAGE ORDINATEUR ═══════════════════
        [
            'slug' => 'banner_ordinateur',
            'label' => 'Ordinateur — Bannière',
            'description' => 'Image en haut de la page /ordinateur (grande bannière hero). Format paysage large (min. 1920×700).',
            'default' => 'images/ordinateur/hero_ordi.jpg',
            'category' => 'ordinateur', 'sortOrder' => 10,
        ],
        [
            'slug' => 'ordi_boost_ssd',
            'label' => 'Ordinateur — Passage au SSD',
            'description' => 'Illustration de la carte "Passage au SSD" (section Boost PC Portable). Format carré ou paysage (min. 800×600).',
            'default' => 'images/ordinateur/boost_ssd.jpg',
            'category' => 'ordinateur', 'sortOrder' => 20,
        ],
        [
            'slug' => 'ordi_boost_ram',
            'label' => 'Ordinateur — Ajout de RAM',
            'description' => 'Illustration de la carte "Ajout de RAM" (section Boost PC Portable). Format carré ou paysage (min. 800×600).',
            'default' => 'images/ordinateur/boost_ram.jpg',
            'category' => 'ordinateur', 'sortOrder' => 30,
        ],
        [
            'slug' => 'ordi_pc_gamer',
            'label' => 'Ordinateur — PC Gamer',
            'description' => 'Illustration de la carte "PC Gamer sur mesure" (section Création). Format carré ou paysage (min. 800×600).',
            'default' => 'images/ordinateur/pc_gamer.jpg',
            'category' => 'ordinateur', 'sortOrder' => 40,
        ],
        [
            'slug' => 'ordi_pc_bureau',
            'label' => 'Ordinateur — PC Bureautique',
            'description' => 'Illustration de la carte "PC Bureautique sur mesure" (section Création). Format carré ou paysage (min. 800×600).',
            'default' => 'images/ordinateur/pc_bureau.jpg',
            'category' => 'ordinateur', 'sortOrder' => 50,
        ],
        [
            'slug' => 'ordi_transfert_donnees',
            'label' => 'Ordinateur — Transfert de données',
            'description' => 'Illustration de la section "Transfert & Copie de données". Format carré ou paysage (min. 800×600).',
            'default' => 'images/ordinateur/transfert_donnees.jpg',
            'category' => 'ordinateur', 'sortOrder' => 60,
        ],

        // ═══════════════════ PAGES LÉGALES ═══════════════════
        [
            'slug' => 'banner_cgv',
            'label' => 'Bannière — CGV',
            'description' => 'Image en haut de la page /cgv (Conditions Générales de Vente). Format paysage (min. 1600×400).',
            'default' => 'images/baner/connexion.png',
            'category' => 'legal', 'sortOrder' => 10,
        ],
        [
            'slug' => 'banner_confidentialite',
            'label' => 'Bannière — Confidentialité',
            'description' => 'Image en haut de la page /politique-de-confidentialite. Format paysage (min. 1600×400).',
            'default' => 'images/baner/connexion.png',
            'category' => 'legal', 'sortOrder' => 20,
        ],
        [
            'slug' => 'banner_mentions_legales',
            'label' => 'Bannière — Mentions légales',
            'description' => 'Image en haut de la page /mentions-legales. Format paysage (min. 1600×400).',
            'default' => 'images/baner/connexion.png',
            'category' => 'legal', 'sortOrder' => 30,
        ],

        // ═══════════════════ PAGES UTILITAIRES ═══════════════════
        [
            'slug' => 'banner_about',
            'label' => 'Bannière — Qui sommes-nous',
            'description' => 'Image en haut de la page /qui-sommes-nous. Format paysage (min. 1600×400).',
            'default' => 'images/baner/quiSommesNous.webp',
            'category' => 'utilitaires', 'sortOrder' => 10,
        ],
        [
            'slug' => 'banner_cart',
            'label' => 'Bannière — Panier',
            'description' => 'Image en haut de la page /panier. Format paysage (min. 1600×400).',
            'default' => 'images/baner/Boutique.png',
            'category' => 'utilitaires', 'sortOrder' => 20,
        ],
        [
            'slug' => 'banner_login',
            'label' => 'Bannière — Connexion',
            'description' => 'Image en haut de la page /login. Format paysage (min. 1600×400).',
            'default' => 'images/baner/connexion.png',
            'category' => 'utilitaires', 'sortOrder' => 30,
        ],
        [
            'slug' => 'banner_account',
            'label' => 'Bannière — Mon Compte',
            'description' => 'Image en haut de la page /compte (espace client connecté). Format paysage (min. 1600×400).',
            'default' => 'images/baner/connexion.png',
            'category' => 'utilitaires', 'sortOrder' => 40,
        ],
        [
            'slug' => 'banner_payment_success',
            'label' => 'Bannière — Paiement réussi',
            'description' => 'Image en haut de la page de confirmation de commande (/paiement/success). Format paysage (min. 1600×400).',
            'default' => 'images/baner/Boutique.png',
            'category' => 'utilitaires', 'sortOrder' => 50,
        ],
        [
            'slug' => 'banner_payment_cancel',
            'label' => 'Bannière — Paiement annulé',
            'description' => 'Image en haut de la page d\'annulation de paiement (/paiement/cancel). Format paysage (min. 1600×400).',
            'default' => 'images/baner/Boutique.png',
            'category' => 'utilitaires', 'sortOrder' => 60,
        ],
    ];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seed des images de site (admin)');

        $repo = $this->em->getRepository(SiteImage::class);
        $created = 0;
        $updated = 0;

        foreach (self::SEEDS as $data) {
            $entity = $repo->findOneBy(['slug' => $data['slug']]);

            if ($entity === null) {
                $entity = (new SiteImage())
                    ->setSlug($data['slug'])
                    ->setLabel($data['label'])
                    ->setDescription($data['description'])
                    ->setDefaultImage($data['default'])
                    ->setCategory($data['category'])
                    ->setSortOrder($data['sortOrder']);
                $this->em->persist($entity);
                $created++;
                $io->writeln(sprintf('  <fg=green>+ CREATE</> %s [%s]', $data['slug'], $data['category']));
            } else {
                // Mettre à jour uniquement les métadonnées (label/desc/default/category/sortOrder)
                // SANS toucher à l'image custom uploadée par l'admin
                $entity
                    ->setLabel($data['label'])
                    ->setDescription($data['description'])
                    ->setDefaultImage($data['default'])
                    ->setCategory($data['category'])
                    ->setSortOrder($data['sortOrder']);
                $updated++;
                $io->writeln(sprintf('  <fg=yellow>~ UPDATE</> %s [%s]', $data['slug'], $data['category']));
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            '%d image(s) créée(s), %d mise(s) à jour.',
            $created,
            $updated
        ));

        $io->note([
            'L\'admin peut maintenant remplacer chaque image depuis /admin/images-site.',
            'Les images uploadées en custom (champ `image`) ne sont PAS écrasées par cette commande.',
        ]);

        return Command::SUCCESS;
    }
}
