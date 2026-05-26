<?php
namespace App\Enum;

enum RcaStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::IN_PROGRESS => 'En cours',
            self::COMPLETED => 'Terminé',
            self::FAILED => 'Échoué',
        };
    }
}
