<?php
require __DIR__ . '/../vendor/autoload.php';
if (class_exists('Symfony\Component\Dotenv\Dotenv')) {
    (new Symfony\Component\Dotenv\Dotenv())->loadEnv(__DIR__ . '/../.env');
}

use App\Kernel;
use Doctrine\ORM\Tools\SchemaTool;

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$meta = $em->getMetadataFactory()->getAllMetadata();

if (empty($meta)) {
    echo "No metadata found, exiting.\n";
    exit(1);
}

$tool = new SchemaTool($em);
try {
    $tool->createSchema($meta);
    echo "Schema created from metadata.\n";
} catch (\Throwable $e) {
    echo "SchemaTool error: " . $e->getMessage() . "\n";
    exit(1);
}

$kernel->shutdown();
