<?php

namespace App\Message;

/**
 * Message Messenger pour le déploiement asynchrone d'un cluster IA
 */
class AIClusterDeploymentMessage
{
    private int $clusterId;

    public function __construct(int $clusterId)
    {
        $this->clusterId = $clusterId;
    }

    public function getClusterId(): int
    {
        return $this->clusterId;
    }
}
