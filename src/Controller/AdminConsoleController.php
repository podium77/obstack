<?php
namespace App\Controller;

use App\Entity\LocalUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin-console', name: 'admin_console_')]
#[IsGranted('ROLE_GLOBAL_ADMIN')]
class AdminConsoleController extends AbstractController
{
    private const ADMIN_CONSOLE_PORT = 5173;
    private const ADMIN_CONSOLE_HOST = 'localhost';
    private const ADMIN_CONSOLE_DIR = __DIR__ . '/../../frontend';

    /**
     * Vérifier l'état du serveur Vue 3
     */
    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $isRunning = $this->isServerRunning();

        return $this->json([
            'running' => $isRunning,
            'port' => self::ADMIN_CONSOLE_PORT,
            'url' => "http://" . self::ADMIN_CONSOLE_HOST . ":" . self::ADMIN_CONSOLE_PORT,
        ]);
    }

    /**
     * Démarrer le serveur Vue 3
     */
    #[Route('/start', name: 'start', methods: ['POST'])]
    public function start(): JsonResponse
    {
        if ($this->isServerRunning()) {
            return $this->json([
                'success' => false,
                'message' => 'Le serveur Admin Console est déjà en cours d\'exécution.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $command = sprintf(
                'cd %s && npm run dev > /dev/null 2>&1 &',
                escapeshellarg(self::ADMIN_CONSOLE_DIR)
            );
            
            shell_exec($command);
            
            // Attendre un peu que le serveur démarre
            sleep(2);
            
            if ($this->isServerRunning()) {
                return $this->json([
                    'success' => true,
                    'message' => 'Serveur Admin Console démarré avec succès.',
                    'url' => "http://" . self::ADMIN_CONSOLE_HOST . ":" . self::ADMIN_CONSOLE_PORT,
                ]);
            } else {
                return $this->json([
                    'success' => false,
                    'message' => 'Le serveur n\'a pas pu démarrer. Vérifiez que npm est installé.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du démarrage: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Arrêter le serveur Vue 3
     */
    #[Route('/stop', name: 'stop', methods: ['POST'])]
    public function stop(): JsonResponse
    {
        if (!$this->isServerRunning()) {
            return $this->json([
                'success' => false,
                'message' => 'Le serveur Admin Console n\'est pas en cours d\'exécution.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            // Tuer tous les processus Vite sur le port 5173
            $command = sprintf(
                'lsof -ti:%d | xargs kill -9 2>/dev/null || true',
                self::ADMIN_CONSOLE_PORT
            );
            
            shell_exec($command);
            
            // Attendre un peu que le processus se termine
            sleep(1);
            
            return $this->json([
                'success' => true,
                'message' => 'Serveur Admin Console arrêté avec succès.',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'arrêt: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Vérifier si le serveur Vue 3 est en cours d'exécution
     */
    private function isServerRunning(): bool
    {
        $fp = @fsockopen(
            self::ADMIN_CONSOLE_HOST,
            self::ADMIN_CONSOLE_PORT,
            $errno,
            $errstr,
            1
        );

        if ($fp) {
            fclose($fp);
            return true;
        }

        return false;
    }
}
