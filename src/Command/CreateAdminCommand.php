<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée (ou promeut) un utilisateur administrateur.',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'administrateur')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $email    = $input->getArgument('email');
        $password = $input->getArgument('password');

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user === null) {
            $user = (new User())
                ->setEmail($email)
                ->setNom('Admin')
                ->setPrenom('Admin')
                ->setStatus('admin')
                ->setPhone('0000000000')
                ->setAdresse('-')
                ->setCodePostal(0)
                ->setVille('-')
                ->setIsVerified(true)
                ->setCreatedAt(new \DateTimeImmutable());
            $created = true;
        } else {
            $created = false;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_ADMIN']);

        if ($created) {
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();

        $io->success(sprintf(
            '%s: %s (ROLE_ADMIN)',
            $created ? 'Administrateur créé' : 'Administrateur mis à jour',
            $email
        ));

        return Command::SUCCESS;
    }
}
