<?php

namespace App\Command;

use App\Entity\LocalUser;
use App\Repository\LocalUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Commande pour réinitialiser le mot de passe d'un utilisateur admin.
 */
#[AsCommand(
    name: 'app:user:reset-password',
    description: 'Réinitialiser le mot de passe d\'un utilisateur',
)]
class ResetPasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private LocalUserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'username',
                null,
                InputOption::VALUE_OPTIONAL,
                'Nom d\'utilisateur (défaut: admin)',
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_OPTIONAL,
                'Nouveau mot de passe',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getOption('username') ?? 'admin';
        $password = $input->getOption('password') ?? null;

        // Chercher l'utilisateur
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            $output->writeln("<error>✗ Utilisateur '$username' non trouvé</error>");
            return Command::FAILURE;
        }

        // Demander le mot de passe s'il n'est pas fourni
        if (!$password) {
            $helper = $this->getHelper('question');
            $question = new Question('Nouveau mot de passe: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }

        if (empty($password)) {
            $output->writeln("<error>✗ Le mot de passe est requis</error>");
            return Command::FAILURE;
        }

        // Hasher et mettre à jour le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $this->em->flush();

        $output->writeln([
            "",
            "<info>✓ Mot de passe réinitialisé avec succès !</info>",
            "",
            "  Utilisateur : $username",
            "  Mot de passe : $password",
            "",
        ]);

        return Command::SUCCESS;
    }
}
