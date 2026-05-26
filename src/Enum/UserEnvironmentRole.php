<?php
namespace App\Enum;

enum UserEnvironmentRole: string {
    case VIEWER   = 'viewer';    // Lecture seule
    case OPERATOR = 'operator';  // Peut déclencher remédiations
    case ADMIN    = 'admin';     // Administre l'environnement
    case OWNER    = 'owner';     // Propriétaire (superadmin délégué)

    public function getLabel(): string {
        return match($this) {
            self::VIEWER   => 'Lecteur',
            self::OPERATOR => 'Opérateur',
            self::ADMIN    => 'Administrateur',
            self::OWNER    => 'Propriétaire',
        };
    }

    public function getWeight(): int {
        return match($this) {
            self::VIEWER   => 1,
            self::OPERATOR => 2,
            self::ADMIN    => 3,
            self::OWNER    => 4,
        };
    }

    public function canView(): bool      { return true; }
    public function canOperate(): bool   { return $this->getWeight() >= 2; }
    public function canAdmin(): bool     { return $this->getWeight() >= 3; }
    public function isOwner(): bool      { return $this === self::OWNER; }
}
