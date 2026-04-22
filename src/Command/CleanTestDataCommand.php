<?php

namespace App\Command;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge les données de test avant la mise en production.
 *
 * CONSERVE :
 *   - Référentiel : Marque, Model, Reparation, SiteImage
 *   - Article : uniquement les 3 PC Gamer (IDs 3, 4, 5)
 *   - User    : uniquement l'admin (dpannephones@outlook.fr)
 *   - GoogleReview : tous (avis Google réels)
 *
 * PURGE :
 *   - Commande, CommandeItem
 *   - Cart, CartItem
 *   - Review (avis clients)
 *   - Partenaire (à remettre à la main en prod)
 *   - Article hors 3 PC Gamer
 *   - User hors admin
 *
 * Usage :
 *   php bin/console app:clean-test-data --dry-run   # simulation
 *   php bin/console app:clean-test-data             # avec confirmation
 *   php bin/console app:clean-test-data --force     # sans confirmation
 */
#[AsCommand(
    name: 'app:clean-test-data',
    description: 'Purge les données de test avant mise en production'
)]
class CleanTestDataCommand extends Command
{
    private const ADMIN_EMAIL = 'dpannephones@outlook.fr';
    private const KEEP_ARTICLE_IDS = [3, 4, 5]; // 3 PC Gamer

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Exécute sans confirmation')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans rien supprimer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $conn = $this->em->getConnection();

        $io->title($dryRun ? 'DRY-RUN : simulation de nettoyage' : 'Nettoyage des données de test');

        // Vérifier que l'admin existe avant de purger quoi que ce soit
        $adminCount = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM user WHERE email = ?',
            [self::ADMIN_EMAIL]
        );
        if ($adminCount === 0) {
            $io->error(sprintf(
                'Utilisateur admin "%s" introuvable. Abandon.',
                self::ADMIN_EMAIL
            ));
            return Command::FAILURE;
        }

        // Vérifier que les 3 PC existent
        $pcCount = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM article WHERE id IN (?)',
            [self::KEEP_ARTICLE_IDS],
            [ArrayParameterType::INTEGER]
        );
        if ($pcCount !== count(self::KEEP_ARTICLE_IDS)) {
            $io->error(sprintf(
                'Seulement %d/%d PC exemple trouvés. Abandon.',
                $pcCount,
                count(self::KEEP_ARTICLE_IDS)
            ));
            return Command::FAILURE;
        }

        // Comptage de ce qui va disparaître
        $stats = [
            ['commande_item',                (int) $conn->fetchOne('SELECT COUNT(*) FROM commande_item')],
            ['commande',                     (int) $conn->fetchOne('SELECT COUNT(*) FROM commande')],
            ['cart_item',                    (int) $conn->fetchOne('SELECT COUNT(*) FROM cart_item')],
            ['cart',                         (int) $conn->fetchOne('SELECT COUNT(*) FROM cart')],
            ['review',                       (int) $conn->fetchOne('SELECT COUNT(*) FROM review')],
            ['partenaire',                   (int) $conn->fetchOne('SELECT COUNT(*) FROM partenaire')],
            ['article (hors 3 PC Gamer)',    (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM article WHERE id NOT IN (?)',
                [self::KEEP_ARTICLE_IDS],
                [ArrayParameterType::INTEGER]
            )],
            ['user (hors admin)',            (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM user WHERE email != ?',
                [self::ADMIN_EMAIL]
            )],
        ];

        $io->section('À SUPPRIMER');
        $io->table(['Table', 'Lignes'], $stats);

        // Comptage de ce qui est conservé (info)
        $kept = [
            ['marque',        (int) $conn->fetchOne('SELECT COUNT(*) FROM marque')],
            ['model',         (int) $conn->fetchOne('SELECT COUNT(*) FROM model')],
            ['reparation',    (int) $conn->fetchOne('SELECT COUNT(*) FROM reparation')],
            ['site_image',    (int) $conn->fetchOne('SELECT COUNT(*) FROM site_image')],
            ['google_review', (int) $conn->fetchOne('SELECT COUNT(*) FROM google_review')],
            ['article (3 PC Gamer)', $pcCount],
            ['user (admin)',  $adminCount],
        ];
        $io->section('À CONSERVER');
        $io->table(['Table', 'Lignes'], $kept);

        if ($dryRun) {
            $io->success('Dry-run terminé — aucune modification effectuée.');
            return Command::SUCCESS;
        }

        if (!$input->getOption('force')) {
            $io->warning('Cette opération est IRRÉVERSIBLE. Assure-toi d\'avoir un backup.');
            if (!$io->confirm('Confirmer le nettoyage ?', false)) {
                $io->warning('Abandonné.');
                return Command::SUCCESS;
            }
        }

        // NOTE : on ne peut PAS wrapper l'ensemble dans une transaction
        // car ALTER TABLE déclenche un commit implicite en MySQL.
        // On fait donc : DELETEs dans une transaction (atomiques) puis ALTER en dehors.
        try {
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');

            $conn->beginTransaction();

            // Ordre : enfants d'abord
            $conn->executeStatement('DELETE FROM commande_item');
            $conn->executeStatement('DELETE FROM commande');
            $conn->executeStatement('DELETE FROM cart_item');
            $conn->executeStatement('DELETE FROM cart');
            $conn->executeStatement('DELETE FROM review');
            $conn->executeStatement('DELETE FROM partenaire');

            $conn->executeStatement(
                'DELETE FROM article WHERE id NOT IN (?)',
                [self::KEEP_ARTICLE_IDS],
                [ArrayParameterType::INTEGER]
            );

            $conn->executeStatement(
                'DELETE FROM user WHERE email != ?',
                [self::ADMIN_EMAIL]
            );

            $conn->commit();

            // Reset auto-increment (hors transaction — DDL = commit implicite)
            foreach (['commande', 'commande_item', 'cart', 'cart_item', 'review', 'partenaire'] as $t) {
                $conn->executeStatement(sprintf('ALTER TABLE %s AUTO_INCREMENT = 1', $t));
            }

            $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');

            $io->success('Nettoyage terminé. Base prête pour la production.');
            $io->note([
                'Prochaines étapes :',
                '1. Tester le site (login admin, boutique, commande test)',
                '2. mysqldump de la base pour import sur le VPS',
            ]);
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            $io->error('Erreur pendant le nettoyage : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
