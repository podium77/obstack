<?php
require __DIR__ . '/../vendor/autoload.php';
if (class_exists('Symfony\Component\Dotenv\Dotenv')) {
    (new Symfony\Component\Dotenv\Dotenv())->loadEnv(__DIR__ . '/../.env');
}

use App\Kernel;
use App\Entity\AgentToken;
use App\Repository\ApplicationRepository;

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$tokenRepo = $em->getRepository(AgentToken::class);
$appRepo = $em->getRepository(ApplicationRepository::class);

$tokens = $tokenRepo->findBy(['application' => null]);
if (empty($tokens)) {
    echo "No unlinked agent tokens found.\n";
    exit(0);
}

$updated = 0;
foreach ($tokens as $token) {
    $env = $token->getEnvironment();
    if (!$env) {
        echo sprintf('Token %d has no environment, skipping.\n', $token->getId());
        continue;
    }

    $app = $appRepo->findByTokenAndEnv($token, $env);
    if (!$app) {
        echo sprintf('Token %d has no matching application in env %d.\n', $token->getId(), $env->getId());
        continue;
    }

    $token->setApplication($app);
    $em->persist($token);
    $updated++;
    echo sprintf('Linked token %d to application %d (%s).\n', $token->getId(), $app->getId(), $app->getName());
}

if ($updated > 0) {
    $em->flush();
}

echo sprintf("Done. %d tokens linked.\n", $updated);
$kernel->shutdown();
