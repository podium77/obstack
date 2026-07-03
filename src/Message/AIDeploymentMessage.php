<?php

namespace App\Message;

/**
 * Messenger Message for Mode 2 AI Deployment
 * 
 * Représente une tâche de déploiement Mode 2 à exécuter de manière asynchrone
 */
class AIDeploymentMessage
{
    private int $deploymentId;

    public function __construct(int $deploymentId)
    {
        $this->deploymentId = $deploymentId;
    }

    public function getDeploymentId(): int
    {
        return $this->deploymentId;
    }
}
