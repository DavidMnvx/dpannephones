<?php
/**
 * Seed de démonstration — crée des articles fictifs pour voir le rendu de la boutique.
 * À exécuter une seule fois (il vérifie les doublons par nom).
 *
 * Usage: php bin/seed-fake-articles.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Kernel;
use App\Entity\Article;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$repo = $em->getRepository(Article::class);

$articles = [
    // ═══════════════════════════════════════
    // PC GAMER (3 configs)
    // ═══════════════════════════════════════
    [
        'name' => 'PC Gamer Entry — Ryzen 5 / RTX 4060',
        'description' => 'Configuration idéale pour débuter sur les FPS compétitifs en 1080p 144Hz. Monté et testé en boutique, garanti 2 ans.',
        'price' => 999.00,
        'grade' => '',
        'image' => 'placeholder-pc-gamer.svg',
        'isNew' => 1,
        'categorie' => 'pc_gamer',
        'specs' => [
            'processeur'   => 'AMD Ryzen 5 7600 — 6 cœurs / 12 threads · 5.1 GHz',
            'carte_graphique' => 'NVIDIA RTX 4060 8 Go GDDR6',
            'ram'          => '16 Go DDR5 5200 MHz',
            'stockage'     => 'SSD NVMe 1 To Gen4',
            'alimentation' => '650W 80+ Bronze',
            'boitier'      => 'ATX Moyen-tour · 2 ventilateurs RGB',
            'refroidissement' => 'Ventirad Air 120mm',
            'os'           => 'Windows 11 Famille',
            'garantie'     => '2 ans pièces et main d\'œuvre',
        ],
    ],
    [
        'name' => 'PC Gamer Performance — Ryzen 7 / RTX 4070 Super',
        'description' => 'La configuration polyvalente pour du gaming haute fréquence 1440p et streaming. Performances solides sur tous les titres AAA.',
        'price' => 1749.00,
        'grade' => '',
        'image' => 'placeholder-pc-gamer.svg',
        'isNew' => 1,
        'categorie' => 'pc_gamer',
        'specs' => [
            'processeur'   => 'AMD Ryzen 7 7700X — 8 cœurs / 16 threads · 5.4 GHz',
            'carte_graphique' => 'NVIDIA RTX 4070 Super 12 Go GDDR6X',
            'ram'          => '32 Go DDR5 6000 MHz (2x16)',
            'stockage'     => 'SSD NVMe 2 To Gen4 + SSD 1 To secondaire',
            'alimentation' => '850W 80+ Gold modulaire',
            'boitier'      => 'ATX · Vitre trempée · 3 ventilateurs RGB',
            'refroidissement' => 'AIO 240mm RGB',
            'os'           => 'Windows 11 Famille',
            'garantie'     => '2 ans pièces et main d\'œuvre',
        ],
    ],
    [
        'name' => 'PC Gamer Elite — Ryzen 9 / RTX 4080 Super',
        'description' => 'Configuration haut de gamme pour gaming 4K, création de contenu et streaming professionnel. Aucun compromis sur les performances.',
        'price' => 2249.00,
        'grade' => '',
        'image' => 'placeholder-pc-gamer.svg',
        'isNew' => 1,
        'categorie' => 'pc_gamer',
        'specs' => [
            'processeur'   => 'AMD Ryzen 9 7900X — 12 cœurs / 24 threads · 5.6 GHz',
            'carte_graphique' => 'NVIDIA RTX 4080 Super 16 Go GDDR6X',
            'ram'          => '64 Go DDR5 6400 MHz (2x32)',
            'stockage'     => 'SSD NVMe 2 To Gen4 + HDD 4 To',
            'alimentation' => '1000W 80+ Platinum modulaire',
            'boitier'      => 'ATX Premium · Vitre trempée · 6 ventilateurs ARGB',
            'refroidissement' => 'AIO 360mm ARGB',
            'os'           => 'Windows 11 Pro',
            'garantie'     => '2 ans pièces et main d\'œuvre',
        ],
    ],

    // ═══════════════════════════════════════
    // PC BUREAUTIQUE (2 configs)
    // ═══════════════════════════════════════
    [
        'name' => 'PC Bureautique Essentiel — Ryzen 5',
        'description' => 'Parfait pour la bureautique, navigation, visioconférence et multi-tâches. Silencieux et économe en énergie.',
        'price' => 549.00,
        'grade' => '',
        'image' => 'placeholder-pc-bureautique.svg',
        'isNew' => 1,
        'categorie' => 'pc_bureautique',
        'specs' => [
            'processeur' => 'AMD Ryzen 5 5600G avec graphique intégré',
            'ram'        => '16 Go DDR4 3200 MHz',
            'stockage'   => 'SSD NVMe 512 Go',
            'connectique' => 'HDMI + DisplayPort + USB-A/C',
            'os'         => 'Windows 11 Famille',
            'garantie'   => '2 ans pièces et main d\'œuvre',
            'ecran'      => 'Non inclus',
        ],
    ],
    [
        'name' => 'PC Bureautique Pro — Intel i5 + Écran 24"',
        'description' => 'Pack complet avec écran 24 pouces Full HD, clavier et souris inclus. Idéal TPE/PME, télétravail et usage professionnel.',
        'price' => 849.00,
        'grade' => '',
        'image' => 'placeholder-pc-bureautique.svg',
        'isNew' => 1,
        'categorie' => 'pc_bureautique',
        'specs' => [
            'processeur' => 'Intel Core i5-13400 — 10 cœurs',
            'ram'        => '16 Go DDR4 3200 MHz',
            'stockage'   => 'SSD NVMe 1 To',
            'ecran'      => '24" Full HD IPS 75Hz — inclus',
            'peripheriques' => 'Clavier + souris filaires inclus',
            'os'         => 'Windows 11 Pro',
            'garantie'   => '2 ans pièces et main d\'œuvre',
        ],
    ],

    // ═══════════════════════════════════════
    // TÉLÉPHONES (5 modèles variés)
    // ═══════════════════════════════════════
    [
        'name' => 'iPhone 14 Pro 256Go - Noir Sidéral',
        'description' => 'iPhone 14 Pro reconditionné Grade A. Écran Super Retina XDR 6.1", puce A16 Bionic, triple caméra pro.',
        'price' => 799.00,
        'grade' => 'A',
        'image' => 'placeholder-telephone-apple.svg',
        'isNew' => 0,
        'categorie' => 'telephone',
        'specs' => [
            'brand' => 'Apple',
            'model' => 'iPhone 14 Pro',
            'os' => 'iOS 17',
            'storage_capacity' => '256 Go',
            'ram' => '6 Go',
            'screen_size' => '6.1"',
            'screen_type' => 'Super Retina XDR OLED',
            'camera' => '48 MP triple',
            'battery_health' => 92,
            'color' => 'Noir Sidéral',
            'network' => '5G',
            'sim' => 'Nano + eSIM',
        ],
    ],
    [
        'name' => 'iPhone 15 128Go - Bleu',
        'description' => 'iPhone 15 neuf, Dynamic Island, port USB-C et puce A16 Bionic. Scellé d\'origine avec garantie constructeur.',
        'price' => 869.00,
        'grade' => '',
        'image' => 'placeholder-telephone-apple.svg',
        'isNew' => 1,
        'categorie' => 'telephone',
        'specs' => [
            'brand' => 'Apple',
            'model' => 'iPhone 15',
            'os' => 'iOS 17',
            'storage_capacity' => '128 Go',
            'ram' => '6 Go',
            'screen_size' => '6.1"',
            'screen_type' => 'Super Retina XDR OLED',
            'camera' => '48 MP + 12 MP',
            'color' => 'Bleu',
            'network' => '5G',
            'sim' => 'Nano + eSIM',
            'connector' => 'USB-C',
        ],
    ],
    [
        'name' => 'Samsung Galaxy S23 256Go - Graphite',
        'description' => 'Galaxy S23 reconditionné Grade A. Écran Dynamic AMOLED 6.1", Snapdragon 8 Gen 2, appareil photo 50 MP.',
        'price' => 549.00,
        'grade' => 'A',
        'image' => 'placeholder-telephone-samsung.svg',
        'isNew' => 0,
        'categorie' => 'telephone',
        'specs' => [
            'brand' => 'Samsung',
            'model' => 'Galaxy S23',
            'os' => 'Android 14 / One UI 6',
            'storage_capacity' => '256 Go',
            'ram' => '8 Go',
            'screen_size' => '6.1"',
            'screen_type' => 'Dynamic AMOLED 2X',
            'camera' => '50 MP triple',
            'battery_health' => 88,
            'color' => 'Graphite',
            'network' => '5G',
            'sim' => 'Nano + eSIM',
        ],
    ],
    [
        'name' => 'Samsung Galaxy A54 128Go - Lime',
        'description' => 'Galaxy A54 neuf, excellent rapport qualité-prix. Écran Super AMOLED 120Hz, batterie 5000 mAh, étanche IP67.',
        'price' => 349.00,
        'grade' => '',
        'image' => 'placeholder-telephone-samsung.svg',
        'isNew' => 1,
        'categorie' => 'telephone',
        'specs' => [
            'brand' => 'Samsung',
            'model' => 'Galaxy A54',
            'os' => 'Android 14 / One UI 6',
            'storage_capacity' => '128 Go',
            'ram' => '8 Go',
            'screen_size' => '6.4"',
            'screen_type' => 'Super AMOLED 120Hz',
            'camera' => '50 MP triple',
            'color' => 'Lime',
            'network' => '5G',
            'sim' => 'Dual SIM',
        ],
    ],
    [
        'name' => 'iPhone 12 64Go - Blanc',
        'description' => 'iPhone 12 reconditionné Grade B, quelques traces d\'usage légères. Fonctionne parfaitement, batterie remplacée.',
        'price' => 329.00,
        'grade' => 'B',
        'image' => 'placeholder-telephone-apple.svg',
        'isNew' => 0,
        'categorie' => 'telephone',
        'specs' => [
            'brand' => 'Apple',
            'model' => 'iPhone 12',
            'os' => 'iOS 17',
            'storage_capacity' => '64 Go',
            'ram' => '4 Go',
            'screen_size' => '6.1"',
            'screen_type' => 'Super Retina XDR OLED',
            'camera' => '12 MP double',
            'battery_health' => 100,
            'color' => 'Blanc',
            'network' => '5G',
            'sim' => 'Nano + eSIM',
            'note' => 'Batterie neuve récemment remplacée en boutique.',
        ],
    ],

    // ═══════════════════════════════════════
    // ACCESSOIRES (5 produits)
    // ═══════════════════════════════════════
    [
        'name' => 'Chargeur USB-C 20W rapide',
        'description' => 'Chargeur mural universel USB-C compatible Power Delivery. Charge rapide pour iPhone 12-17 et smartphones Android récents.',
        'price' => 14.90,
        'grade' => '',
        'image' => 'placeholder-accessoire-chargeur.svg',
        'isNew' => 1,
        'categorie' => 'accessoires',
        'specs' => [
            'puissance' => '20W Power Delivery',
            'prise' => 'USB-C (câble non inclus)',
            'compatibilite' => 'iPhone 12 et +, Samsung, Google Pixel, Xiaomi',
            'certification' => 'CE · RoHS',
        ],
    ],
    [
        'name' => 'Câble USB-C vers Lightning 1m — Tressé',
        'description' => 'Câble renforcé tressé en nylon. Charge rapide + synchronisation. Certifié compatible Apple.',
        'price' => 12.90,
        'grade' => '',
        'image' => 'placeholder-accessoire-chargeur.svg',
        'isNew' => 1,
        'categorie' => 'accessoires',
        'specs' => [
            'longueur' => '1 mètre',
            'connecteurs' => 'USB-C mâle / Lightning mâle',
            'revetement' => 'Nylon tressé anti-nœuds',
            'compatibilite' => 'iPhone 5 à iPhone 14',
            'charge' => 'Fast Charge compatible',
        ],
    ],
    [
        'name' => 'Écouteurs Bluetooth sans fil — Pro Buds',
        'description' => 'Écouteurs True Wireless avec réduction de bruit active. Autonomie 24h avec boîtier. Son cristallin et micro intégré.',
        'price' => 39.90,
        'grade' => '',
        'image' => 'placeholder-accessoire-ecouteurs.svg',
        'isNew' => 1,
        'categorie' => 'accessoires',
        'specs' => [
            'connectivite' => 'Bluetooth 5.3',
            'autonomie' => '6h + 18h avec boîtier',
            'reduction_bruit' => 'ANC active',
            'resistance' => 'IPX5 - résistant à la transpiration',
            'compatibilite' => 'iOS / Android / Windows',
            'couleur' => 'Blanc',
        ],
    ],
    [
        'name' => 'Coque silicone antichoc iPhone 15 Pro',
        'description' => 'Coque souple transparente avec bords renforcés. Protection chocs et chutes jusqu\'à 2 mètres. Accès complet aux boutons.',
        'price' => 9.90,
        'grade' => '',
        'image' => 'placeholder-accessoire-coque.svg',
        'isNew' => 1,
        'categorie' => 'accessoires',
        'specs' => [
            'modele_compatible' => 'iPhone 15 Pro',
            'materiau' => 'Silicone TPU transparent',
            'protection' => 'Chocs, rayures, chutes jusqu\'à 2m',
            'couleur' => 'Transparent',
            'compatibilite_magsafe' => 'Oui',
        ],
    ],
    [
        'name' => 'Support de téléphone voiture magnétique',
        'description' => 'Support universel à aimants puissants, fixation grille d\'aération. Rotation 360°, installation en 5 secondes.',
        'price' => 16.90,
        'grade' => '',
        'image' => 'placeholder-accessoire-chargeur.svg',
        'isNew' => 1,
        'categorie' => 'accessoires',
        'specs' => [
            'type' => 'Magnétique grille d\'aération',
            'rotation' => '360°',
            'compatibilite' => 'Tous smartphones (plaque métal incluse)',
            'installation' => 'Sans outil',
            'couleur' => 'Noir',
        ],
    ],
];

$added = 0;
$skipped = 0;

foreach ($articles as $data) {
    // Éviter les doublons par nom
    if ($repo->findOneBy(['name' => $data['name']])) {
        echo "  ↷ Déjà présent : {$data['name']}\n";
        $skipped++;
        continue;
    }

    $a = new Article();
    $a->setName($data['name']);
    $a->setDescription($data['description']);
    $a->setPrice($data['price']);
    $a->setGrade($data['grade']);
    $a->setImage($data['image']);
    $a->setIsNew((bool) $data['isNew']);
    $a->setCategorie($data['categorie']);
    $a->setSpecs($data['specs']);

    $em->persist($a);
    $added++;
    echo "  ✓ Ajouté : {$data['name']}\n";
}

$em->flush();

echo "\n";
echo "═══════════════════════════════════════\n";
echo "  Articles ajoutés  : {$added}\n";
echo "  Articles ignorés  : {$skipped}\n";
echo "═══════════════════════════════════════\n";
