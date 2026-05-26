<?php
require 'vendor/autoload.php';
require 'config/bootstrap.php';

use App\Repository\ApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Container;

$kernel = new \App\Kernel($_SERVER['APP_ENV'] ?? 'dev', $_SERVER['APP_DEBUG'] ?? true);
$kernel->boot();
$container = $kernel->getContainer();

/** @var EntityManagerInterface $em */
$em = $container->get('doctrine.orm.entity_manager');

/** @var ApplicationRepository $appRepo */
$appRepo = $em->getRepository(\App\Entity\Application::class);

// Get first environment
$envRepo = $em->getRepository(\App\Entity\Environment::class);
$env = $envRepo->findOneBy([]);

if (!$env) {
    echo "No environments found\n";
    exit(1);
}

echo "Environment: " . $env->getName() . "\n";
echo "---\n";

// Get apps with agent tokens
$apps = $appRepo->findAllActiveByEnvironment($env);
echo "Total apps: " . count($apps) . "\n";
echo "\n";

foreach ($apps as $app) {
    echo "App: " . $app->getName() . " (ID: " . $app->getId() . ")\n";
    echo "  Agent Token: " . ($app->getAgentToken() ? 'YES' : 'NO') . "\n";
    if ($app->getAgentToken()) {
        $modules = $app->getAgentToken()->getModules();
        echo "  Modules: " . json_encode($modules) . "\n";
        echo "  Module count: " . count($modules) . "\n";
    }
    echo "\n";
}
