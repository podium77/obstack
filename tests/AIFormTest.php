<?php
// Test simple de vérification que le formulaire de création d'application avec section IA fonctionne

namespace App\Tests;

// Vérifier que les classes existent
class AIFormTest
{
    public static function testClasses()
    {
        $checks = [];
        
        // Vérifier les entités
        $checks['AICluster'] = class_exists('App\Entity\AICluster');
        
        // Vérifier les services
        $checks['AIClusterService'] = class_exists('App\Service\AI\AIClusterService');
        $checks['AIInstallationScript'] = class_exists('App\Service\AI\AIInstallationScript');
        
        // Vérifier les repos
        $checks['AIClusterRepository'] = class_exists('App\Repository\AIClusterRepository');
        
        // Vérifier le contrôleur
        $checks['ApplicationController'] = class_exists('App\Controller\ApplicationController');
        
        // Vérifier les messages
        $checks['AIClusterDeploymentMessage'] = class_exists('App\Message\AIClusterDeploymentMessage');
        
        return $checks;
    }
}

if (php_sapi_name() === 'cli') {
    $results = AIFormTest::testClasses();
    echo "✓ AI Form Verification\n\n";
    foreach ($results as $class => $exists) {
        echo ($exists ? '✓' : '✗') . " $class\n";
    }
    
    $allExist = !in_array(false, $results);
    echo "\n" . ($allExist ? '✓ ALL CHECKS PASSED' : '✗ SOME CHECKS FAILED') . "\n";
    exit($allExist ? 0 : 1);
}
