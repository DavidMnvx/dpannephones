<?php

namespace App\Command;

use App\Entity\Commande;
use App\Entity\Review;
use App\Repository\CommandeRepository;
use App\Repository\ReviewRepository;
use App\Service\ReviewNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Envoie une invitation "Laissez un avis" aux clients dont la commande
 * a été livrée/retirée/payée il y a X jours (par défaut 10 jours).
 *
 * À lancer quotidiennement via cron sur le VPS :
 *   0 10 * * *  cd /var/www/dpannephones && php bin/console app:send-review-invitations
 */
#[AsCommand(
    name: 'app:send-review-invitations',
    description: 'Envoie les invitations "Laissez un avis" aux clients après leur commande'
)]
class SendReviewInvitationsCommand extends Command
{
    // Statuts de commande éligibles (le client a bien reçu son article)
    private const ELIGIBLE_STATUSES = ['livree', 'retiree', 'expediee', 'payee'];

    public function __construct(
        private CommandeRepository       $commandeRepo,
        private ReviewRepository         $reviewRepo,
        private ReviewNotificationService $notif,
        private EntityManagerInterface   $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_OPTIONAL, 'Nombre de jours après la commande', 10)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans envoyer les emails')
            ->addOption('max', 'm', InputOption::VALUE_OPTIONAL, 'Limite du nombre d\'emails à envoyer', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $days    = (int) $input->getOption('days');
        $dryRun  = (bool) $input->getOption('dry-run');
        $max     = (int) $input->getOption('max');

        $io->title('Invitations "Laissez un avis" — commandes de J-' . $days);

        // Date seuil (commandes créées avant cette date)
        $thresholdDate = (new \DateTime())->modify('-' . $days . ' days');
        $lowerBound    = (clone $thresholdDate)->modify('-1 day'); // fenêtre de 24h pour éviter doublons

        // On sélectionne les commandes :
        //  - statut éligible
        //  - créées entre J-11 et J-10 (fenêtre étroite, exécution quotidienne)
        //  - ayant un utilisateur associé
        //  - pour lesquelles le user n'a pas déjà laissé d'avis
        $qb = $this->commandeRepo->createQueryBuilder('c')
            ->andWhere('c.statut IN (:statuses)')
            ->andWhere('c.createdAt >= :from')
            ->andWhere('c.createdAt <= :to')
            ->andWhere('c.user IS NOT NULL')
            ->setParameter('statuses', self::ELIGIBLE_STATUSES)
            ->setParameter('from', $lowerBound)
            ->setParameter('to', $thresholdDate)
            ->orderBy('c.createdAt', 'ASC')
            ->setMaxResults($max);

        $commandes = $qb->getQuery()->getResult();

        if (empty($commandes)) {
            $io->success('Aucune commande éligible pour l\'envoi aujourd\'hui.');
            return Command::SUCCESS;
        }

        $io->info(sprintf(
            '%d commande(s) éligibles trouvées (créées entre le %s et le %s)',
            count($commandes),
            $lowerBound->format('d/m/Y'),
            $thresholdDate->format('d/m/Y')
        ));

        $sent    = 0;
        $skipped = 0;

        foreach ($commandes as $commande) {
            /** @var Commande $commande */

            // Vérifier si l'utilisateur a déjà laissé un avis pour cette commande
            if ($this->reviewRepo->hasUserReviewedCommande($commande->getUser(), $commande->getId())) {
                $io->writeln(sprintf('  ↷ Commande #%d : le client a déjà laissé un avis — ignorée', $commande->getId()));
                $skipped++;
                continue;
            }

            $io->writeln(sprintf(
                '  → Commande #%d : envoi à %s (%s)',
                $commande->getId(),
                $commande->getUser()->getEmail(),
                $commande->getCreatedAt()->format('d/m/Y')
            ));

            if (!$dryRun) {
                try {
                    $this->notif->sendReviewInvitation($commande);
                    $sent++;
                } catch (\Exception $e) {
                    $io->error(sprintf('Échec pour #%d : %s', $commande->getId(), $e->getMessage()));
                }
            } else {
                $sent++; // simulation
            }
        }

        $io->newLine();
        $io->success(sprintf(
            '%s %d email(s) d\'invitation — %d ignoré(s) (avis déjà laissé)',
            $dryRun ? '[DRY-RUN] Aurait envoyé' : 'Envoyé',
            $sent,
            $skipped
        ));

        return Command::SUCCESS;
    }
}
