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
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Commande pour créer le premier utilisateur admin global.
 * 
 * Cet utilisateur a accès total au système et ne peut pas être supprimé.
 */
#[AsCommand(
    name: 'app:user:create-admin',
    description: 'Créer un utilisateur admin global (superadmin)',
)]
class CreateAdminCommand extends Command
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
                'email',
                null,
                InputOption::VALUE_OPTIONAL,
                'Adresse email de l\'admin',
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_OPTIONAL,
                'Mot de passe de l\'admin',
            )
            ->addOption(
                'name',
                null,
                InputOption::VALUE_OPTIONAL,
                'Nom de l\'admin',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Vérifier s'il existe déjà un admin
        $existingAdmin = $this->userRepository->findOneBy(['email' => 'admin']);
        if ($existingAdmin) {
            $io->warning('Un utilisateur admin existe déjà.');
            return Command::SUCCESS;
        }

        // Collecter les informations
        $email = $input->getOption('email') ?? $this->askEmail($input, $output);
        $name = $input->getOption('name') ?? $this->askName($input, $output);
        $password = $input->getOption('password') ?? $this->askPassword($input, $output);

        try {
            // Créer l'utilisateur
            $admin = new LocalUser();
            $admin->setEmail($email);
            $admin->setUsername('admin'); // Login unique
            $admin->setDisplayName($name);
            $admin->setIsGlobalAdmin(true); // Flag spécial
            $admin->setActive(true);

            // Hasher le mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($admin, $password);
            $admin->setPassword($hashedPassword);

            $this->em->persist($admin);
            $this->em->flush();

            $io->success('✓ Utilisateur admin créé avec succès!');
            $io->writeln([
                '',
                '  Email: ' . $email,
                '  Login: admin',
                '  Display Name: ' . $name,
                '  Global Admin: Yes',
                '',
                '  🔑 Permissions: GLOBAL_ADMIN (all permissions inherited)',
                '',
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors de la création: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function askEmail(InputInterface $input, OutputInterface $output): string
    {
        $helper = $this->getHelper('question');
        $question = new Question('Email de l\'admin: ');
        $question->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \Exception('L\'email est requis');
            }
            if (!filter_var($answer, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Email invalide');
            }
            $existing = $this->userRepository->findOneBy(['email' => $answer]);
            if ($existing) {
                throw new \Exception('Cet email existe déjà');
            }
            return $answer;
        });
        $question->setMaxAttempts(3);

        return $helper->ask($input, $output, $question);
    }

    private function askName(InputInterface $input, OutputInterface $output): string
    {
        $helper = $this->getHelper('question');
        $question = new Question('Nom de l\'admin: ', 'Administrator');

        return $helper->ask($input, $output, $question);
    }

    private function askPassword(InputInterface $input, OutputInterface $output): string
    {
        $helper = $this->getHelper('question');
        $question = new Question('Mot de passe: ');
        $question->setHidden(true);
        $question->setValidator(function ($answer) {
            if (strlen($answer) < 8) {
                throw new \Exception('Le mot de passe doit faire au moins 8 caractères');
            }
            return $answer;
        });
        $question->setMaxAttempts(3);

        return $helper->ask($input, $output, $question);
    }
}
