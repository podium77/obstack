<?php
require __DIR__ . '/../vendor/autoload.php';
// Load .env into \\$_ENV so kernel/container can resolve env parameters
if (class_exists('Symfony\\Component\\Dotenv\\Dotenv')) {
    (new Symfony\Component\Dotenv\Dotenv())->loadEnv(__DIR__ . '/../.env');
}

use App\Kernel;

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
/** @var \Doctrine\ORM\EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();
$meta = $em->getMetadataFactory()->getAllMetadata();
echo "METADATA COUNT: " . count($meta) . "\n";
foreach ($meta as $m) {
    echo $m->getName() . "\n";
}
// show connection params
$conn = $em->getConnection();
$params = $conn->getParams();
if (isset($params['url'])) {
    echo "DB URL (param): " . $params['url'] . "\n";
} else {
    echo "DB driver: " . ($params['driver'] ?? '(none)') . " host: " . ($params['host'] ?? '(none)') . " dbname: " . ($params['dbname'] ?? '(none)') . " user: " . ($params['user'] ?? '(none)') . "\n";
}

$kernel->shutdown();
