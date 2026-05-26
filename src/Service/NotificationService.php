<?php
namespace App\Service;

use App\Entity\Company;
use App\Entity\Environment;
use App\Entity\LocalUser;
use App\Repository\CompanyRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\LocalUserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NotificationService
{
    private array $slackWebhooks = [];
    private array $teamsWebhooks = [];

    public function __construct(
        private readonly TenantContext       $tenant,
        private readonly MailerInterface     $mailer,
        private readonly LoggerInterface     $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly CompanyRepository   $companyRepo,
        private readonly string              $defaultSender,
        private readonly string $mailAdmin,
        private readonly ?string $slackWebhookUrl,
        private readonly ?string $teamsWebhookUrl,
    ) {
        // Charger les webhooks depuis la config ou la base de données
        $this->loadWebhooks();
    }

    /**
     * Notifier les administrateurs d'une entreprise.
     */
    public function notifyAdmins(
        Company $company,
        string $subject,
        string $message,
        string $type = 'info',
        ?Environment $environment = null
    ): void {
        $admins = $this->getCompanyAdmins($company);

        foreach ($admins as $admin) {
            $this->notifyUser(
                $admin,
                $subject,
                $message,
                $type,
                $environment
            );
        }
    }

    /**
     * Notifier un utilisateur spécifique.
     */
    public function notifyUser(
        LocalUser $user,
        string $subject,
        string $message,
        string $type = 'info',
        ?Environment $environment = null
    ): void {
        // 1. Notification par email
        $this->sendEmailNotification($user, $subject, $message, $type, $environment);

        // 2. Notification Slack (si configuré)
        $this->sendSlackNotification($user, $subject, $message, $type, $environment);

        // 3. Notification Teams (si configuré)
        $this->sendTeamsNotification($user, $subject, $message, $type, $environment);

        $this->logger->info("Notification envoyée à {user} : {subject}", [
            'user' => $user->getEmail(),
            'subject' => $subject,
            'type' => $type,
        ]);
    }

    /**
     * Envoyer une notification par email.
     */
    public function sendEmailNotification(
        LocalUser $user,
        string $subject,
        string $message,
        string $type = 'info',
        ?Environment $environment = null
    ): void {
        try {
            $email = (new Email())
                ->from($this->defaultSender)
                ->to($user->getEmail())
                ->subject($subject)
                ->html($this->formatEmailMessage($message, $type, $environment));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error("Échec de l'envoi de l'email à {email}: {error}", [
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoyer une notification Slack.
     */
    public function sendSlackNotification(
        LocalUser $user,
        string $subject,
        string $message,
        string $type = 'info',
        ?Environment $environment = null
    ): void {
        $company = $user->getCompany();
        if (!$company || !isset($this->slackWebhooks[$company->getId()])) {
            return;
        }

        $webhookUrl = $this->slackWebhooks[$company->getId()];
        if (!$webhookUrl) {
            return;
        }

        try {
            $color = match ($type) {
                'error' => '#ff0000',
                'warning' => '#ffcc00',
                'success' => '#00aa00',
                default => '#36a64f',
            };

            $payload = [
                'text' => $subject,
                'attachments' => [[
                    'color' => $color,
                    'title' => $subject,
                    'text' => $message,
                    'fields' => [
                        [
                            'title' => 'Environnement',
                            'value' => $environment ? $environment->getName() : 'Tous',
                            'short' => true,
                        ],
                        [
                            'title' => 'Date',
                            'value' => (new \DateTime())->format('Y-m-d H:i:s'),
                            'short' => true,
                        ],
                    ],
                ]],
            ];

            $this->httpClient->request('POST', $webhookUrl, [
                'json' => $payload,
            ]);
        } catch (\Exception $e) {
            $this->logger->error("Échec de l'envoi de la notification Slack: {error}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoyer une notification Microsoft Teams.
     */
    public function sendTeamsNotification(
        LocalUser $user,
        string $subject,
        string $message,
        string $type = 'info',
        ?Environment $environment = null
    ): void {
        $company = $user->getCompany();
        if (!$company || !isset($this->teamsWebhooks[$company->getId()])) {
            return;
        }

        $webhookUrl = $this->teamsWebhooks[$company->getId()];
        if (!$webhookUrl) {
            return;
        }

        try {
            $color = match ($type) {
                'error' => 'FF0000',
                'warning' => 'FFCC00',
                'success' => '00AA00',
                default => '36A64F',
            };

            $payload = [
                '@type' => 'MessageCard',
                '@context' => 'http://schema.org/extensions',
                'themeColor' => $color,
                'summary' => $subject,
                'sections' => [[
                    'activityTitle' => $subject,
                    'activitySubtitle' => $environment ? "Environnement: {$environment->getName()}" : 'Tous les environnements',
                    'text' => $message,
                ]],
            ];

            $this->httpClient->request('POST', $webhookUrl, [
                'json' => $payload,
            ]);
        } catch (\Exception $e) {
            $this->logger->error("Échec de l'envoi de la notification Teams: {error}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notifier pour une alerte spécifique.
     */
    public function notifyAlert(
        \App\Entity\Alert $alert,
        string $message,
        string $type = 'warning'
    ): void {
        $company = $alert->getApplication()->getEnvironment()->getCompany();
        $subject = "[obstack] Alerte {$alert->getSeverity()->getLabel()} : {$alert->getTitle()}";

        $this->notifyAdmins(
            $company,
            $subject,
            $message,
            $type,
            $alert->getApplication()->getEnvironment()
        );
    }

    /**
     * Notifier pour une remédiation déclenchée.
     */
    public function notifyRemediation(
        \App\Entity\RemediationLog $log,
        string $message
    ): void {
        $company = $log->getApplication()->getEnvironment()->getCompany();
        $subject = "[obstack] Remédiation déclenchée : {$log->getAction()->getLabel()}";

        $this->notifyAdmins(
            $company,
            $subject,
            $message,
            'info',
            $log->getApplication()->getEnvironment()
        );
    }

    // --- Méthodes privées ---

    private function getCompanyAdmins(Company $company): array
    {
        // Récupérer les utilisateurs avec le rôle SUPERADMIN ou ADMIN pour cette entreprise
        return $this->companyRepo->findAdmins($company);
    }

    private function loadWebhooks(): void
    {
        // Charger les webhooks depuis la base de données ou la config
        // Exemple: $this->slackWebhooks = $this->companyRepo->findAllSlackWebhooks();
        // Pour l'instant, on initialise vide (à implémenter selon votre structure)
        $this->slackWebhooks = [];
        $this->teamsWebhooks = [];
    }

    private function formatEmailMessage(string $message, string $type, ?Environment $environment): string
    {
        $color = match ($type) {
            'error' => '#ff4444',
            'warning' => '#ffbb33',
            'success' => '#00C851',
            default => '#33b5e5',
        };

        $envName = $environment ? $environment->getName() : 'Tous les environnements';

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: {$color}; color: white; padding: 10px; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; background-color: #f9f9f9; border-radius: 0 0 5px 5px; }
                .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2 style="margin: 0;">obstack - Notification</h2>
                </div>
                <div class="content">
                    <p><strong>Environnement :</strong> {$envName}</p>
                    <p><strong>Type :</strong> {$type}</p>
                    <hr>
                    <p>{$message}</p>
                </div>
                <div class="footer">
                    <p>Ce message a été généré automatiquement par obstack.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}
