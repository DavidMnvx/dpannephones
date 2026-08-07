<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\ProductColor;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Repository\CategoryRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\File;

class ArticleType extends AbstractType
{
    public function __construct(private CategoryRepository $categoryRepo)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ── Base fields ──────────────────────────────────────────────────
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr'  => ['rows' => 3],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix (€)',
                'scale' => 2,
            ])
            ->add('grade', ChoiceType::class, [
                'label'       => 'Grade',
                'required'    => false,
                'choices'     => [
                    'Neuf'              => 'Neuf',
                    'A+ (Comme neuf)'   => 'A+',
                    'A (Très bon état)' => 'A',
                    'B (Bon état)'      => 'B',
                    'C (État correct)'  => 'C',
                ],
                'placeholder' => 'Aucun grade',
            ])
            ->add('categorie', ChoiceType::class, [
                'label'       => 'Catégorie',
                'required'    => false,
                'placeholder' => 'Choisir une catégorie',
                'choices'     => array_flip($this->categoryRepo->getSlugLabelMap()),
            ])
            ->add('isNew', ChoiceType::class, [
                'label'    => "État de l'article",
                'choices'  => [
                    'Neuf'        => true,
                    "D'occasion"  => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'help'     => "Un article d'occasion apparaît automatiquement dans le rayon « Occasions » de la boutique, en plus de sa catégorie.",
            ])
            ->add('isFeatured', CheckboxType::class, [
                'label'    => 'Mettre en avant dans la bannière de la boutique',
                'required' => false,
            ])
            ->add('photos', FileType::class, [
                'label'       => "Photos de l'article",
                'mapped'      => false,
                'multiple'    => true,
                'required'    => false,
                'attr'        => ['accept' => 'image/*', 'style' => 'display:none;'],
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\All([
                        'constraints' => [
                            new File([
                                'maxSize'          => '4M',
                                'mimeTypes'        => ['image/jpeg', 'image/png', 'image/webp'],
                                'mimeTypesMessage' => 'Photos : JPEG, PNG ou WebP uniquement (max 4 Mo par fichier).',
                            ]),
                        ],
                    ]),
                ],
            ])

            // ══════════════════════════════════════════════════════
            // PC GAMER — spécifications détaillées
            // ══════════════════════════════════════════════════════
            ->add('gamer_cpu', TextType::class, [
                'label'    => 'Processeur',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. AMD Ryzen 7 7700X — 8 cœurs / 16 threads'],
            ])
            ->add('gamer_gpu', TextType::class, [
                'label'    => 'Carte graphique',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. NVIDIA RTX 4070 Super 12 Go'],
            ])
            ->add('gamer_ram', TextType::class, [
                'label'    => 'Mémoire vive (RAM)',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 32 Go DDR5 6000 MHz'],
            ])
            ->add('gamer_storage', TextType::class, [
                'label'    => 'Stockage',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. SSD NVMe 1 To + HDD 2 To'],
            ])
            ->add('gamer_motherboard', TextType::class, [
                'label'    => 'Carte mère',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. ASUS TUF B650-PLUS'],
            ])
            ->add('gamer_psu', TextType::class, [
                'label'    => 'Alimentation',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 750W 80+ Gold modulaire'],
            ])
            ->add('gamer_case', TextType::class, [
                'label'    => 'Boîtier',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. ATX Moyen-tour vitre trempée'],
            ])
            ->add('gamer_cooling', TextType::class, [
                'label'    => 'Refroidissement',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. AIO 240mm ARGB'],
            ])
            ->add('gamer_connectivity', TextType::class, [
                'label'    => 'Connectique',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 4× USB-A 3.0, 2× USB-C, HDMI, DisplayPort, Jack 3.5mm'],
            ])
            ->add('gamer_network', TextType::class, [
                'label'    => 'Réseau',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. WiFi 6E, Bluetooth 5.3, Ethernet 2.5 Gbps'],
            ])
            ->add('gamer_os', TextType::class, [
                'label'    => "Système d'exploitation",
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Windows 11 Famille'],
            ])
            ->add('gamer_peripherals', TextType::class, [
                'label'    => 'Périphériques inclus',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Aucun / Clavier + souris RGB inclus'],
            ])
            ->add('gamer_screen_included', TextType::class, [
                'label'    => 'Écran inclus',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Non inclus / 27" QHD 165Hz inclus'],
            ])
            ->add('gamer_rgb', ChoiceType::class, [
                'label'    => 'Éclairage RGB',
                'mapped'   => false,
                'required' => false,
                'placeholder' => '—',
                'choices'  => [
                    'Oui (ventilateurs + boîtier)' => 'Oui',
                    'Partiel' => 'Partiel',
                    'Non' => 'Non',
                ],
            ])
            ->add('gamer_warranty', TextType::class, [
                'label'    => 'Garantie',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 2 ans pièces et main d\'œuvre'],
            ])

            // ══════════════════════════════════════════════════════
            // PC BUREAUTIQUE — spécifications détaillées
            // ══════════════════════════════════════════════════════
            ->add('bureau_cpu', TextType::class, [
                'label'    => 'Processeur',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Intel Core i5-13400'],
            ])
            ->add('bureau_gpu', TextType::class, [
                'label'    => 'Carte graphique',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Intel UHD Graphics intégrée / GTX 1650'],
            ])
            ->add('bureau_ram', TextType::class, [
                'label'    => 'Mémoire vive (RAM)',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 16 Go DDR4 3200 MHz'],
            ])
            ->add('bureau_storage', TextType::class, [
                'label'    => 'Stockage',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. SSD NVMe 512 Go'],
            ])
            ->add('bureau_motherboard', TextType::class, [
                'label'    => 'Carte mère',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. ASUS Prime B660'],
            ])
            ->add('bureau_connectivity', TextType::class, [
                'label'    => 'Connectique',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. HDMI + DisplayPort + USB-A/C + Jack'],
            ])
            ->add('bureau_network', TextType::class, [
                'label'    => 'Réseau',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. WiFi 6, Bluetooth 5.2, Ethernet'],
            ])
            ->add('bureau_os', TextType::class, [
                'label'    => "Système d'exploitation",
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Windows 11 Pro'],
            ])
            ->add('bureau_screen', TextType::class, [
                'label'    => 'Écran inclus',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Non inclus / 24" Full HD IPS inclus'],
            ])
            ->add('bureau_peripherals', TextType::class, [
                'label'    => 'Périphériques inclus',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Clavier + souris filaires inclus'],
            ])
            ->add('bureau_warranty', TextType::class, [
                'label'    => 'Garantie',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 2 ans pièces et main d\'œuvre'],
            ])

            // ══════════════════════════════════════════════════════
            // PC PORTABLE — spécifications détaillées
            // ══════════════════════════════════════════════════════
            ->add('portable_cpu', TextType::class, [
                'label'    => 'Processeur',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Intel Core i7-13700H'],
            ])
            ->add('portable_gpu', TextType::class, [
                'label'    => 'Carte graphique',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. NVIDIA RTX 4060 Laptop'],
            ])
            ->add('portable_ram', TextType::class, [
                'label'    => 'RAM',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 16 Go DDR5'],
            ])
            ->add('portable_storage', TextType::class, [
                'label'    => 'Stockage',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. SSD NVMe 1 To'],
            ])
            ->add('portable_screen', TextType::class, [
                'label'    => 'Taille écran',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 15.6"'],
            ])
            ->add('portable_screen_type', TextType::class, [
                'label'    => "Type d'écran",
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. IPS Full HD 144Hz / OLED 4K'],
            ])
            ->add('portable_resolution', TextType::class, [
                'label'    => 'Résolution',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 1920 × 1080'],
            ])
            ->add('portable_os', TextType::class, [
                'label'    => "Système d'exploitation",
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Windows 11 Famille'],
            ])
            ->add('portable_battery', TextType::class, [
                'label'    => 'Autonomie batterie',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Jusqu\'à 8h en usage bureautique'],
            ])
            ->add('portable_weight', TextType::class, [
                'label'    => 'Poids',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 2.1 kg'],
            ])
            ->add('portable_keyboard', TextType::class, [
                'label'    => 'Clavier',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. AZERTY rétroéclairé RGB'],
            ])
            ->add('portable_webcam', TextType::class, [
                'label'    => 'Webcam',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. HD 720p avec micro intégré'],
            ])
            ->add('portable_connectivity', TextType::class, [
                'label'    => 'Connectique',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 2× USB-C Thunderbolt 4, 2× USB-A, HDMI, Jack, lecteur SD'],
            ])
            ->add('portable_network', TextType::class, [
                'label'    => 'Réseau',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. WiFi 6E, Bluetooth 5.3'],
            ])
            ->add('portable_warranty', TextType::class, [
                'label'    => 'Garantie',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 2 ans constructeur'],
            ])

            // ══════════════════════════════════════════════════════
            // ACCESSOIRES — spécifications typées (type + fields adaptés)
            // ══════════════════════════════════════════════════════
            ->add('acc_type', ChoiceType::class, [
                'label'       => "Type d'accessoire",
                'mapped'      => false,
                'required'    => false,
                'placeholder' => "Choisir un type d'accessoire",
                'choices'     => [
                    'Chargeur / Adaptateur secteur' => 'chargeur',
                    'Câble'                          => 'cable',
                    'Écouteurs / Casque'             => 'audio',
                    // 'Coque / Housse' retiré : les coques ont désormais leur propre catégorie
                    'Protection écran'               => 'protection_ecran',
                    'Support / Fixation'             => 'support',
                    'Batterie externe'               => 'batterie',
                    'Autre accessoire'               => 'autre',
                ],
            ])
            ->add('acc_brand', TextType::class, [
                'label'    => 'Marque',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Anker, Belkin, Apple, Samsung…'],
            ])
            ->add('acc_compatibility', TextType::class, [
                'label'    => 'Compatibilité',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. iPhone 12 à 15, Samsung Galaxy S20+'],
            ])
            ->add('acc_material', TextType::class, [
                'label'    => 'Matériau',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Silicone TPU, Cuir synthétique…'],
            ])
            // acc_color : retiré — les couleurs sont maintenant assignées par photo directement
            // via le photo picker (Photo.color_id). Cf. handleArticlePhotos + template.
            ->add('acc_connector', TextType::class, [
                'label'    => 'Connectique / Prise',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. USB-C, Lightning, Jack 3.5mm'],
            ])
            ->add('acc_power', TextType::class, [
                'label'    => 'Puissance / Capacité',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 20W Power Delivery / 10000 mAh'],
            ])
            ->add('acc_technology', TextType::class, [
                'label'    => 'Technologie / Norme',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Power Delivery, Quick Charge, MagSafe, USB 3.1…'],
            ])
            ->add('acc_ports_count', TextType::class, [
                'label'    => 'Nombre de ports',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 2 USB-C + 1 USB-A'],
            ])
            ->add('acc_length', TextType::class, [
                'label'    => 'Longueur',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 1m, 1.5m, 2m…'],
            ])
            ->add('acc_certifications', TextType::class, [
                'label'    => 'Certifications',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. CE, MFi, RoHS, PD 3.0…'],
            ])
            ->add('acc_battery_life', TextType::class, [
                'label'    => 'Autonomie',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 24h avec boîtier, 6h en écoute'],
            ])
            ->add('acc_water_resistance', TextType::class, [
                'label'    => 'Résistance à l\'eau',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. IPX4, IP68, Résistant aux éclaboussures'],
            ])
            ->add('acc_contents', TextType::class, [
                'label'    => 'Contenu de la boîte',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Chargeur + câble USB-C 1m + notice'],
            ])
            ->add('acc_warranty', TextType::class, [
                'label'    => 'Garantie',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. 2 ans'],
            ])

            // ── Film Hydrogel specs ───────────────────────────────────────────
            ->add('hydrogel_models', TextareaType::class, [
                'label'    => 'Modèles compatibles (une marque par ligne)',
                'help'     => 'Format : "Marque: modèle1, modèle2, modèle3" — une ligne par marque.',
                'mapped'   => false,
                'required' => false,
                'attr'     => [
                    'rows'        => 8,
                    'placeholder' => "Apple: iPhone 12, iPhone 13, iPhone 14, iPhone 15\nSamsung: Galaxy S22, Galaxy S23, Galaxy S24\nXiaomi: Redmi Note 12, Poco X5",
                ],
            ])

            // ── Coques specs ──────────────────────────────────────────────────
            ->add('coque_models', TextareaType::class, [
                'label'    => 'Modèles compatibles (une marque par ligne)',
                'help'     => 'Format : "Marque: modèle1, modèle2, modèle3" — une ligne par marque.',
                'mapped'   => false,
                'required' => false,
                'attr'     => [
                    'rows'        => 8,
                    'placeholder' => "Apple: iPhone 13, iPhone 14, iPhone 15\nSamsung: Galaxy S23, Galaxy A54\nXiaomi: Redmi Note 12",
                ],
            ])

            // ── Téléphone — Identification ────────────────────────────────────
            ->add('tel_brand', ChoiceType::class, [
                'label'       => 'Marque ★',
                'mapped'      => false,
                'required'    => false,
                'placeholder' => 'Choisir une marque',
                'choices'     => [
                    'Apple'    => 'Apple',
                    'Samsung'  => 'Samsung',
                    'Google'   => 'Google',
                    'Xiaomi'   => 'Xiaomi',
                    'OnePlus'  => 'OnePlus',
                    'Huawei'   => 'Huawei',
                    'Sony'     => 'Sony',
                    'Nokia'    => 'Nokia',
                    'Motorola' => 'Motorola',
                    'Oppo'     => 'Oppo',
                    'Realme'   => 'Realme',
                    'Autre'    => 'Autre',
                ],
            ])
            ->add('tel_model', TextType::class, [
                'label'       => 'Modèle (ex: iPhone 13)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => 'iPhone 13 Pro Max'],
            ])
            ->add('tel_os', ChoiceType::class, [
                'label'       => "Système d'exploitation",
                'mapped'      => false,
                'required'    => false,
                'placeholder' => 'Choisir',
                'choices'     => [
                    'iOS'     => 'iOS',
                    'Android' => 'Android',
                ],
            ])
            ->add('tel_year', TextType::class, [
                'label'       => 'Année de sortie',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '2021'],
            ])

            // ── Téléphone — Écran ─────────────────────────────────────────────
            ->add('tel_screen_size', TextType::class, [
                'label'       => 'Taille écran (ex: 6.1")',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '6.1"'],
            ])
            ->add('tel_screen_type', TextType::class, [
                'label'       => "Type d'écran (ex: OLED, Super AMOLED…)",
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => 'OLED, HDR10'],
            ])
            ->add('tel_resolution', TextType::class, [
                'label'       => 'Résolution (ex: 2532 x 1170)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '2532 x 1170'],
            ])

            // ── Téléphone — Performances ──────────────────────────────────────
            ->add('tel_processor', TextType::class, [
                'label'       => 'Processeur (ex: Apple A15 Bionic)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => 'Apple A15 Bionic'],
            ])
            ->add('tel_ram', TextType::class, [
                'label'       => 'RAM (ex: 4 Go)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '4 Go'],
            ])
            ->add('tel_storage', TextType::class, [
                'label'       => 'Stockage (ex: 128 Go)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '128 Go'],
            ])

            // ── Téléphone — Photo ─────────────────────────────────────────────
            ->add('tel_camera', TextType::class, [
                'label'       => 'Appareil photo principal (ex: 12 MP)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '12 MP'],
            ])
            ->add('tel_camera_front', TextType::class, [
                'label'       => 'Appareil photo frontal (ex: 12 MP)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '12 MP'],
            ])
            ->add('tel_other_cameras', TextType::class, [
                'label'       => 'Autres caméras (ex: Ultra grand angle 12 MP)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '2ème caméra 12 MP Ultra grand angle'],
            ])

            // ── Téléphone — Connectivité ──────────────────────────────────────
            ->add('tel_network', TextType::class, [
                'label'       => 'Réseau (4G / 5G)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '5G'],
            ])
            ->add('tel_sim', TextType::class, [
                'label'       => 'SIM (ex: Dual-SIM, eSIM, Nano-SIM)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => 'Dual-SIM (eSIM, Nano-SIM)'],
            ])
            ->add('tel_connectivity', TextType::class, [
                'label'       => 'Connectivité (WiFi, Bluetooth, NFC…)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => 'WiFi 802.11a/b/g/n/ac/ax, Bluetooth 5.0, NFC, UWB, 5G'],
            ])
            ->add('tel_connector', TextType::class, [
                'label'       => 'Connectique (ex: Lightning, USB-C)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => 'Lightning'],
            ])

            // ── Téléphone — Batterie & physique ──────────────────────────────
            ->add('tel_color', TextType::class, [
                'label'       => 'Couleur',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => 'ex: Vert, Noir, Blanc'],
            ])
            ->add('tel_battery_capacity', TextType::class, [
                'label'       => 'Capacité batterie (ex: 3227 mAh)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '3227 mAh'],
            ])
            ->add('tel_battery', TextType::class, [
                'label'       => 'Santé batterie (%)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '87'],
            ])
            ->add('tel_weight', TextType::class, [
                'label'       => 'Poids (ex: 174 g)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '174 g'],
            ])
            ->add('tel_dimensions', TextType::class, [
                'label'       => 'Dimensions (ex: 71.5 x 146.7 x 7.7 mm)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => '71.5 x 146.7 x 7.7 mm'],
            ])
            ->add('tel_sensors', TextType::class, [
                'label'       => 'Capteurs (ex: Face ID, accéléromètre…)',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => 'accéléromètre, gyroscope, Face ID...'],
            ])

            // ── Téléphone — Obligation légale DAS ────────────────────────────
            ->add('tel_sar', TextType::class, [
                'label'       => '⚠️ DAS (W/kg) — Obligatoire légalement',
                'mapped'      => false,
                'required'    => false,
                'attr'        => ['placeholder' => 'ex: Tête 1.08 W/kg — Tronc 1.17 W/kg'],
                'help'        => 'Le Débit d\'Absorption Spécifique (DAS) doit être affiché. Consultez le manuel du fabricant.',
            ])

            // ── Téléphone — Note reconditionneur ─────────────────────────────
            ->add('tel_note', TextareaType::class, [
                'label'    => 'Note sur le reconditionnement',
                'mapped'   => false,
                'required' => false,
                'attr'     => [
                    'rows'        => 2,
                    'placeholder' => 'ex: Après le reconditionnement, le certificat IPxx ne peut plus être garanti...',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}
