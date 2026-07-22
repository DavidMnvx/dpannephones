<?php

namespace App\Command;

use App\Entity\AppSetting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Initialise les paramètres globaux du site (AppSetting) avec leurs valeurs par défaut.
 *
 * Idempotente : crée les settings manquants, met à jour label/description/type
 * sans toucher à la valeur personnalisée de l'admin.
 *
 * Usage :
 *   php bin/console app:seed-app-settings
 */
#[AsCommand(
    name: 'app:seed-app-settings',
    description: 'Initialise les paramètres globaux du site (par défaut : feature désactivée)'
)]
class SeedAppSettingsCommand extends Command
{
    /**
     * Liste des paramètres disponibles.
     * category = section affichée dans l'admin /admin/parametres
     * default  = valeur appliquée uniquement à la création (pas lors des updates)
     */
    private const SEEDS = [
        // ═══════════════════════════════════════════════════════════════
        // 🚧 MODE MAINTENANCE & DÉSACTIVATION DE PAGES
        // ═══════════════════════════════════════════════════════════════
        [
            'key'         => 'site_maintenance_mode',
            'label'       => 'Site en maintenance (bloquer tout le site public)',
            'description' => 'Si activé : les visiteurs non-administrateurs sont redirigés vers la page de connexion. Utilisez-le pendant la préparation du catalogue ou en cas de problème. L\'admin continue de voir le site normalement.',
            'type'        => AppSetting::TYPE_BOOL,
            'default'     => '0', // Désactivé par défaut
            'category'    => 'site',
            'sortOrder'   => 10,
        ],
        [
            'key'         => 'page_boutique_enabled',
            'label'       => 'Page Boutique activée',
            'description' => 'Si décoché : /boutique redirige vers l\'accueil pour les visiteurs. L\'admin continue de la voir.',
            'type'        => AppSetting::TYPE_BOOL,
            'default'     => '1',
            'category'    => 'site',
            'sortOrder'   => 20,
        ],
        [
            'key'         => 'page_reparations_enabled',
            'label'       => 'Page Réparations activée',
            'description' => 'Si décoché : /reparations (et ses sous-pages) sont indisponibles. Pratique si le catalogue de tarifs n\'est pas encore rempli.',
            'type'        => AppSetting::TYPE_BOOL,
            'default'     => '1',
            'category'    => 'site',
            'sortOrder'   => 30,
        ],
        [
            'key'         => 'page_ordinateur_enabled',
            'label'       => 'Page Ordinateur activée',
            'description' => 'Si décoché : /ordinateur redirige vers l\'accueil.',
            'type'        => AppSetting::TYPE_BOOL,
            'default'     => '1',
            'category'    => 'site',
            'sortOrder'   => 40,
        ],
        [
            'key'         => 'page_partenaires_enabled',
            'label'       => 'Page Partenaires activée',
            'description' => 'Si décoché : /partenaires est indisponible. Utile tant qu\'aucun partenaire n\'a été ajouté.',
            'type'        => AppSetting::TYPE_BOOL,
            'default'     => '1',
            'category'    => 'site',
            'sortOrder'   => 50,
        ],
        [
            'key'         => 'page_reviews_enabled',
            'label'       => 'Page Avis clients activée',
            'description' => 'Si décoché : /reviews est indisponible.',
            'type'        => AppSetting::TYPE_BOOL,
            'default'     => '1',
            'category'    => 'site',
            'sortOrder'   => 60,
        ],
        [
            'key'         => 'page_contact_enabled',
            'label'       => 'Page Contact activée',
            'description' => 'Si décoché : /contact est indisponible. ⚠️ Attention : cette page est souvent le canal principal des clients.',
            'type'        => AppSetting::TYPE_BOOL,
            'default'     => '1',
            'category'    => 'site',
            'sortOrder'   => 70,
        ],
        [
            'key'         => 'page_about_enabled',
            'label'       => 'Page Qui sommes-nous activée',
            'description' => 'Si décoché : /qui-sommes-nous est indisponible.',
            'type'        => AppSetting::TYPE_BOOL,
            'default'     => '1',
            'category'    => 'site',
            'sortOrder'   => 80,
        ],

        // ═══════════════════════════════════════════════════════════════
        // 🎁 RÉCOMPENSE AVIS CLIENT (envoi d'un code promo après avis)
        // ═══════════════════════════════════════════════════════════════
        [
            'key'         => 'review_reward_enabled',
            'label'       => 'Activer les récompenses avis',
            'description' => 'Si activé : chaque client qui poste un avis (quelle que soit la note) reçoit automatiquement un code promo unique par email.',
            'type'        => AppSetting::TYPE_BOOL,
            'default'     => '0', // Désactivé par défaut (attente validation client)
            'category'    => 'review_reward',
            'sortOrder'   => 10,
        ],
        [
            'key'         => 'review_reward_discount_type',
            'label'       => 'Type de remise',
            'description' => 'Pourcentage (ex: -10%) ou Montant fixe (ex: -10€).',
            'type'        => AppSetting::TYPE_STRING,
            'default'     => 'percentage', // 'percentage' | 'fixed'
            'category'    => 'review_reward',
            'sortOrder'   => 20,
        ],
        [
            'key'         => 'review_reward_discount_value',
            'label'       => 'Valeur de la remise',
            'description' => 'Entrez 10 pour -10% (si pourcentage) ou 10 pour -10€ (si montant fixe).',
            'type'        => AppSetting::TYPE_FLOAT,
            'default'     => '10',
            'category'    => 'review_reward',
            'sortOrder'   => 30,
        ],
        [
            'key'         => 'review_reward_min_cart',
            'label'       => 'Montant minimum du panier (€)',
            'description' => 'Montant TTC du panier nécessaire pour utiliser le code. Laisse 0 pour pas de minimum.',
            'type'        => AppSetting::TYPE_FLOAT,
            'default'     => '30',
            'category'    => 'review_reward',
            'sortOrder'   => 40,
        ],
        [
            'key'         => 'review_reward_validity_days',
            'label'       => 'Durée de validité du code (jours)',
            'description' => 'Nombre de jours avant expiration du code envoyé au client. Recommandé : 90 jours (3 mois).',
            'type'        => AppSetting::TYPE_INT,
            'default'     => '90',
            'category'    => 'review_reward',
            'sortOrder'   => 50,
        ],

        // ═══════════════════════════════════════════════════════════════
        // 📣 ACCROCHES BOUTIQUE (phrase de réassurance par catégorie)
        // ═══════════════════════════════════════════════════════════════
        [
            'key'         => 'boutique_accroche_pc_gamer',
            'label'       => 'Accroche — PC Gamer',
            'description' => 'Phrase affichée au-dessus des articles de la catégorie PC Gamer. Laissez vide pour ne rien afficher.',
            'type'        => AppSetting::TYPE_STRING,
            'default'     => "Montage rapide et soigné avec D'panne Phones — chaque PC est assemblé, testé et garanti 2 ans dans notre atelier de Pélissanne.",
            'category'    => 'boutique_accroche',
            'sortOrder'   => 10,
        ],
        [
            'key'         => 'boutique_accroche_pc_bureautique',
            'label'       => 'Accroche — PC Bureautique',
            'description' => 'Phrase affichée au-dessus des articles de la catégorie PC Bureautique. Laissez vide pour ne rien afficher.',
            'type'        => AppSetting::TYPE_STRING,
            'default'     => '',
            'category'    => 'boutique_accroche',
            'sortOrder'   => 20,
        ],
        [
            'key'         => 'boutique_accroche_pc_portable',
            'label'       => 'Accroche — PC Portable',
            'description' => 'Phrase affichée au-dessus des articles de la catégorie PC Portable. Laissez vide pour ne rien afficher.',
            'type'        => AppSetting::TYPE_STRING,
            'default'     => '',
            'category'    => 'boutique_accroche',
            'sortOrder'   => 30,
        ],
        [
            'key'         => 'boutique_accroche_accessoires',
            'label'       => 'Accroche — Accessoires',
            'description' => 'Phrase affichée au-dessus des articles de la catégorie Accessoires. Laissez vide pour ne rien afficher.',
            'type'        => AppSetting::TYPE_STRING,
            'default'     => '',
            'category'    => 'boutique_accroche',
            'sortOrder'   => 40,
        ],
        [
            'key'         => 'boutique_accroche_telephone',
            'label'       => 'Accroche — Téléphones',
            'description' => 'Phrase affichée au-dessus des articles de la catégorie Téléphones. Laissez vide pour ne rien afficher.',
            'type'        => AppSetting::TYPE_STRING,
            'default'     => '',
            'category'    => 'boutique_accroche',
            'sortOrder'   => 50,
        ],

        // ═══════════════════════════════════════════════════════════════
        // 🚚 LIVRAISON
        // ═══════════════════════════════════════════════════════════════
        [
            'key'         => 'mondial_relay_brand',
            'label'       => 'Code enseigne Mondial Relay',
            'description' => 'Code enseigne fourni par Mondial Relay à l\'ouverture de votre contrat (8 caractères). Laissez "BDTEST" tant que vous n\'avez pas de contrat : le sélecteur de point relais fonctionnera en mode démonstration.',
            'type'        => AppSetting::TYPE_STRING,
            'default'     => 'BDTEST',
            'category'    => 'livraison',
            'sortOrder'   => 10,
        ],
    ];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seed des paramètres globaux du site');

        $repo = $this->em->getRepository(AppSetting::class);
        $created = 0;
        $updated = 0;

        foreach (self::SEEDS as $data) {
            $entity = $repo->findOneBy(['key' => $data['key']]);

            if ($entity === null) {
                $entity = (new AppSetting())
                    ->setKey($data['key'])
                    ->setType($data['type'])
                    ->setRawValue($data['default'])
                    ->setLabel($data['label'])
                    ->setDescription($data['description'])
                    ->setCategory($data['category'])
                    ->setSortOrder($data['sortOrder']);
                $this->em->persist($entity);
                $created++;
                $io->writeln(sprintf(
                    '  <fg=green>+ CREATE</> %s = <fg=cyan>%s</>',
                    $data['key'],
                    $data['default']
                ));
            } else {
                // Mise à jour uniquement des métadonnées (PAS la valeur, qui peut avoir été modifiée par l'admin)
                $entity
                    ->setType($data['type'])
                    ->setLabel($data['label'])
                    ->setDescription($data['description'])
                    ->setCategory($data['category'])
                    ->setSortOrder($data['sortOrder']);
                $updated++;
                $io->writeln(sprintf(
                    '  <fg=yellow>~ UPDATE</> %s (metadata only, value preserved)',
                    $data['key']
                ));
            }
        }

        $this->em->flush();

        $io->success(sprintf('%d paramètre(s) créé(s), %d mis à jour.', $created, $updated));
        $io->note([
            'L\'admin peut régler ces paramètres depuis /admin/parametres.',
            'Les valeurs déjà personnalisées ne sont PAS écrasées par cette commande.',
        ]);

        return Command::SUCCESS;
    }
}
