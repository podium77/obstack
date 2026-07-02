<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Activity Digest Service - Generate and send activity digest emails
 */
class ActivityDigestService
{
    private const DIGEST_TYPES = ['daily', 'weekly', 'monthly'];

    public function __construct(
        private Connection $connection,
        private MailerInterface $mailer,
    ) {}

    /**
     * Generate digest for user
     */
    public function generateDigest(int $userId, string $frequency = 'daily'): array
    {
        try {
            if (!in_array($frequency, self::DIGEST_TYPES)) {
                return ['success' => false, 'error' => 'Invalid frequency'];
            }

            $lookbackHours = match ($frequency) {
                'daily' => 24,
                'weekly' => 7 * 24,
                'monthly' => 30 * 24,
            };

            $date = (new \DateTime())
                ->sub(new \DateInterval('PT' . $lookbackHours . 'H'))
                ->format('Y-m-d H:i:s');

            // Get user workspaces
            $workspaces = $this->connection->fetchAllAssociative(
                'SELECT DISTINCT w.id, w.name FROM workspaces w 
                 JOIN workspace_members wm ON w.id = wm.workspace_id 
                 WHERE wm.user_id = ?',
                [$userId]
            );

            $digest = [
                'user_id' => $userId,
                'frequency' => $frequency,
                'period' => $frequency . '_' . date('Y-m-d'),
                'generated_at' => (new \DateTime())->format('c'),
                'workspaces' => [],
            ];

            foreach ($workspaces as $workspace) {
                $activities = $this->connection->fetchAllAssociative(
                    'SELECT * FROM activity_feed WHERE workspace_id = ? AND created_at > ? ORDER BY created_at DESC',
                    [$workspace['id'], $date]
                );

                $comments = $this->connection->fetchAllAssociative(
                    'SELECT c.* FROM comments c 
                     JOIN queries q ON c.query_id = q.id 
                     JOIN workspace_queries wq ON q.id = wq.query_id
                     WHERE wq.workspace_id = ? AND c.created_at > ?',
                    [$workspace['id'], $date]
                );

                $digest['workspaces'][] = [
                    'name' => $workspace['name'],
                    'activity_count' => count($activities),
                    'comment_count' => count($comments),
                    'activities' => array_slice($activities, 0, 10),
                    'top_comments' => array_slice($comments, 0, 5),
                ];
            }

            return ['success' => true, 'data' => $digest];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send digest email
     */
    public function sendDigest(int $userId, string $frequency = 'daily'): array
    {
        try {
            // Generate digest content
            $digestResult = $this->generateDigest($userId, $frequency);
            if (!$digestResult['success']) {
                return $digestResult;
            }

            $digest = $digestResult['data'];

            // Get user email
            $user = $this->connection->fetchAssociative(
                'SELECT email, display_name FROM local_user WHERE id = ?',
                [$userId]
            );

            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }

            // Build HTML content
            $htmlContent = $this->buildDigestHtml($digest, $user['display_name']);

            // Send email
            $email = (new Email())
                ->from('notifications@obstack.local')
                ->to($user['email'])
                ->subject(ucfirst($frequency) . ' Activity Digest - Obstack')
                ->html($htmlContent);

            $this->mailer->send($email);

            // Log digest sent
            $this->connection->insert('digest_logs', [
                'user_id' => $userId,
                'frequency' => $frequency,
                'sent_at' => (new \DateTime())->format('c'),
                'workspace_count' => count($digest['workspaces']),
            ]);

            return ['success' => true, 'message' => 'Digest email sent'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send digests to all users for scheduled frequency
     */
    public function sendScheduledDigests(string $frequency): array
    {
        try {
            $users = $this->connection->fetchAllAssociative(
                'SELECT DISTINCT u.id FROM local_user u 
                 JOIN notification_preferences np ON u.id = np.user_id 
                 WHERE np.digest_frequency = ?',
                [$frequency]
            );

            $sent = 0;
            foreach ($users as $user) {
                $result = $this->sendDigest($user['id'], $frequency);
                if ($result['success']) {
                    $sent++;
                }
            }

            return ['success' => true, 'data' => ['total_users' => count($users), 'digests_sent' => $sent]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get digest history for user
     */
    public function getDigestHistory(int $userId, int $limit = 20): array
    {
        try {
            $history = $this->connection->fetchAllAssociative(
                'SELECT * FROM digest_logs WHERE user_id = ? ORDER BY sent_at DESC LIMIT ?',
                [$userId, $limit]
            );

            return ['success' => true, 'data' => $history];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get digest statistics
     */
    public function getDigestStats(string $frequency): array
    {
        try {
            $stats = [
                'total_sent' => $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM digest_logs WHERE frequency = ?',
                    [$frequency]
                ),
                'last_sent' => $this->connection->fetchOne(
                    'SELECT MAX(sent_at) FROM digest_logs WHERE frequency = ?',
                    [$frequency]
                ),
                'subscribers' => $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM notification_preferences WHERE digest_frequency = ?',
                    [$frequency]
                ),
            ];

            return ['success' => true, 'data' => $stats];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build digest HTML
     */
    private function buildDigestHtml(array $digest, string $userName): string
    {
        $html = '<html><body>';
        $html .= '<h1>Your ' . ucfirst($digest['frequency']) . ' Activity Digest</h1>';
        $html .= '<p>Hi ' . $userName . ',</p>';

        foreach ($digest['workspaces'] as $ws) {
            $html .= '<h2>' . $ws['name'] . '</h2>';
            $html .= '<p><strong>Activities:</strong> ' . $ws['activity_count'] . '</p>';
            $html .= '<p><strong>Comments:</strong> ' . $ws['comment_count'] . '</p>';

            if (!empty($ws['activities'])) {
                $html .= '<h3>Recent Activities</h3><ul>';
                foreach ($ws['activities'] as $activity) {
                    $html .= '<li>' . $activity['description'] . '</li>';
                }
                $html .= '</ul>';
            }
        }

        $html .= '<p>Generated: ' . $digest['generated_at'] . '</p>';
        $html .= '</body></html>';

        return $html;
    }
}
