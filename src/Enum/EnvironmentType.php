<?php
namespace App\Enum;

enum EnvironmentType: string {
    case PRODUCTION  = 'production';
    case STAGING     = 'staging';
    case DEVELOPMENT = 'development';
    case LAB         = 'lab';
    case QA          = 'qa';
    case DR          = 'dr'; // Disaster Recovery
    case DEFAULT     = 'default';

    public function getLabel(): string {
        return match($this) {
            self::PRODUCTION  => 'Production',
            self::STAGING     => 'Pré-production',
            self::DEVELOPMENT => 'Développement',
            self::LAB         => 'Laboratoire',
            self::QA          => 'Assurance Qualité',
            self::DR          => 'Disaster Recovery',
            self::DEFAULT     => 'Environnement par défaut',
        };
    }
    public function getBadgeColor(): string {
        return match($this) {
            self::PRODUCTION  => '#E24B4A',
            self::STAGING     => '#EF9F27',
            self::DEVELOPMENT => '#185FA5',
            self::LAB         => '#8B5CF6',
            self::QA          => '#10B981',
            self::DR          => '#6B7280',
            self::DEFAULT     => '#185FA5',
        };
    }
}
