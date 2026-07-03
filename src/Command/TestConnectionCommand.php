<?php

namespace App\Command;

use App\Repository\DatabaseConnectionRepository;
use App\Service\AdminService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-connection', description: 'Test une connexion de base de données')]
class TestConnectionCommand extends Command
{
    public function __construct(
        private DatabaseConnectionRepository $connectionRepository,
        private AdminService $adminService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'Connection ID', 6);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');
        $output->writeln("<info>🧪 Test connexion #{$id}</info>\n");

        $conn = $this->connectionRepository->find($id);

        if (!$conn) {
            $output->writeln("<fg=red>❌ Connexion not found</>");
            return Command::FAILURE;
        }

        $output->writeln("<comment>Connexion: {$conn->getName()}</comment>");
        $output->writeln("  Type: {$conn->getType()}");
        $output->writeln("  Host: {$conn->getHost()}:{$conn->getPort()}");
        $output->writeln("  User: {$conn->getUsername()}");
        $output->writeln("  DB: {$conn->getDatabase()}\n");

        try {
            $output->writeln("Testing...\n");
            $result = $this->adminService->testDatabaseConnection($conn);
            
            if ($result['success']) {
                $output->writeln("<fg=green>✅ SUCCESS: {$result['message']}</>");
            } else {
                $output->writeln("<fg=yellow>⚠️  FAILED: {$result['message']}</>");
                if (isset($result['error'])) {
                    $output->writeln("  Error: {$result['error']}");
                }
            }
        } catch (\Exception $e) {
            $output->writeln("<fg=red>❌ EXCEPTION: {$e->getMessage()}</>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
