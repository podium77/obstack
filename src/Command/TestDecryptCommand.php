<?php

namespace App\Command;

use App\Repository\DatabaseConnectionRepository;
use App\Service\PasswordEncryptionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-decrypt', description: 'Test déchiffrement du mot de passe')]
class TestDecryptCommand extends Command
{
    public function __construct(
        private DatabaseConnectionRepository $connectionRepository,
        private PasswordEncryptionService $passwordEncryption,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>🔍 Test déchiffrement des mots de passe</info>');
        $output->writeln('');

        $connections = $this->connectionRepository->findAll();

        foreach ($connections as $conn) {
            $output->writeln("<comment>Connexion #{$conn->getId()}: {$conn->getName()}</comment>");
            $output->writeln("  📌 Type: {$conn->getType()}");
            $output->writeln("  🔌 Host: {$conn->getHost()}:{$conn->getPort()}");
            $output->writeln("  👤 User: {$conn->getUsername()}");
            $output->writeln("  📦 DB: {$conn->getDatabase()}");
            
            $encrypted = $conn->getEncryptedPassword();
            if (!$encrypted) {
                $output->writeln("  🔐 Mot de passe: VIDE");
            } else {
                $output->writeln("  🔒 Chiffré: " . substr($encrypted, 0, 20) . "...");
                
                try {
                    $decrypted = $this->passwordEncryption->decrypt($encrypted);
                    $output->writeln("  ✅ Déchiffré: <fg=green>$decrypted</>");
                } catch (\Exception $e) {
                    $output->writeln("  ❌ Erreur déchiffrement: <fg=red>" . $e->getMessage() . "</>");
                }
            }
            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
