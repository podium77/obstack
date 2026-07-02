<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Notification Settings Service - Manage user notification preferences
 */
class NotificationSettingsService
{
    private const DEFAULT_PREFERENCES = [
        'notify_mentions' => true,
        'notify_replies' => true,
        'notify_reactions' => true,
        'notify_votes' => true,
        'digest_frequency' => 'daily',
        'quiet_hours_enabled' => false,
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '08:00',
    ];

    public function __construct(private Connection $connection) {}

    /**
     * Get user settings
     */
    public function getUserSettings(int $userId): array
    {
        try {
            $settings = $this->connection->fetchAssociative(
                'SELECT * FROM notification_preferences WHERE user_id = ?',
                [$userId]
            );

            if (!$settings) {
                return ['success' => true, 'data' => self::DEFAULT_PREFERENCES];
            }

            return ['success' => true, 'data' => $settings];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update user settings
     */
    public function updateSettings(int $userId, array $settings): array
    {
        try {
            $exists = $this->connection->fetchOne(
                'SELECT id FROM notification_preferences WHERE user_id = ?',
                [$userId]
            );

            if ($exists) {
                $this->connection->update('notification_preferences', $settings, ['user_id' => $userId]);
            } else {
                $settings['user_id'] = $userId;
                $this->connection->insert('notification_preferences', $settings);
            }

            return ['success' => true, 'message' => 'Settings updated successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enable/disable notification type
     */
    public function toggleNotificationType(int $userId, string $type, bool $enabled): array
    {
        try {
            $validTypes = ['notify_mentions', 'notify_replies', 'notify_reactions', 'notify_votes'];

            if (!in_array($type, $validTypes)) {
                return ['success' => false, 'error' => 'Invalid notification type'];
            }

            $this->updateSettings($userId, [$type => $enabled]);

            return ['success' => true, 'message' => ucfirst(str_replace('_', ' ', $type)) . ' notifications ' . ($enabled ? 'enabled' : 'disabled')];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Set digest frequency
     */
    public function setDigestFrequency(int $userId, string $frequency): array
    {
        try {
            $validFrequencies = ['never', 'daily', 'weekly', 'monthly'];

            if (!in_array($frequency, $validFrequencies)) {
                return ['success' => false, 'error' => 'Invalid frequency'];
            }

            $this->updateSettings($userId, ['digest_frequency' => $frequency]);

            return ['success' => true, 'message' => 'Digest frequency updated to ' . $frequency];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Set quiet hours
     */
    public function setQuietHours(int $userId, string $startTime, string $endTime, bool $enabled = true): array
    {
        try {
            // Validate time format (HH:MM)
            if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
                return ['success' => false, 'error' => 'Invalid time format. Use HH:MM'];
            }

            $this->updateSettings($userId, [
                'quiet_hours_enabled' => $enabled,
                'quiet_hours_start' => $startTime,
                'quiet_hours_end' => $endTime,
            ]);

            return ['success' => true, 'message' => 'Quiet hours updated'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if in quiet hours
     */
    public function isInQuietHours(int $userId): array
    {
        try {
            $settings = $this->connection->fetchAssociative(
                'SELECT quiet_hours_enabled, quiet_hours_start, quiet_hours_end FROM notification_preferences WHERE user_id = ?',
                [$userId]
            );

            if (!$settings || !$settings['quiet_hours_enabled']) {
                return ['success' => true, 'data' => ['in_quiet_hours' => false]];
            }

            $now = new \DateTime();
            $currentTime = $now->format('H:i');

            $startTime = $settings['quiet_hours_start'];
            $endTime = $settings['quiet_hours_end'];

            $inQuietHours = false;

            if ($startTime < $endTime) {
                // Normal case: quiet hours don't cross midnight
                $inQuietHours = $currentTime >= $startTime && $currentTime < $endTime;
            } else {
                // Quiet hours cross midnight
                $inQuietHours = $currentTime >= $startTime || $currentTime < $endTime;
            }

            return ['success' => true, 'data' => ['in_quiet_hours' => $inQuietHours]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get workspace notification preferences
     */
    public function getWorkspacePreferences(int $userId, string $workspaceId): array
    {
        try {
            $prefs = $this->connection->fetchAssociative(
                'SELECT * FROM workspace_notification_preferences WHERE user_id = ? AND workspace_id = ?',
                [$userId, $workspaceId]
            );

            if (!$prefs) {
                return ['success' => true, 'data' => [
                    'mute_workspace' => false,
                    'mute_all_comments' => false,
                ]];
            }

            return ['success' => true, 'data' => $prefs];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update workspace preferences
     */
    public function updateWorkspacePreferences(int $userId, string $workspaceId, array $preferences): array
    {
        try {
            $exists = $this->connection->fetchOne(
                'SELECT id FROM workspace_notification_preferences WHERE user_id = ? AND workspace_id = ?',
                [$userId, $workspaceId]
            );

            if ($exists) {
                $this->connection->update(
                    'workspace_notification_preferences',
                    $preferences,
                    ['user_id' => $userId, 'workspace_id' => $workspaceId]
                );
            } else {
                $preferences['user_id'] = $userId;
                $preferences['workspace_id'] = $workspaceId;
                $this->connection->insert('workspace_notification_preferences', $preferences);
            }

            return ['success' => true, 'message' => 'Workspace preferences updated'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mute workspace
     */
    public function muteWorkspace(int $userId, string $workspaceId): array
    {
        return $this->updateWorkspacePreferences($userId, $workspaceId, ['mute_workspace' => true]);
    }

    /**
     * Unmute workspace
     */
    public function unmuteWorkspace(int $userId, string $workspaceId): array
    {
        return $this->updateWorkspacePreferences($userId, $workspaceId, ['mute_workspace' => false]);
    }

    /**
     * Get muted workspaces
     */
    public function getMutedWorkspaces(int $userId): array
    {
        try {
            $workspaces = $this->connection->fetchAllAssociative(
                'SELECT w.id, w.name FROM workspaces w 
                 JOIN workspace_notification_preferences wnp ON w.id = wnp.workspace_id 
                 WHERE wnp.user_id = ? AND wnp.mute_workspace = true',
                [$userId]
            );

            return ['success' => true, 'data' => $workspaces];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reset settings to defaults
     */
    public function resetToDefaults(int $userId): array
    {
        try {
            $this->updateSettings($userId, self::DEFAULT_PREFERENCES);
            return ['success' => true, 'message' => 'Settings reset to defaults'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
