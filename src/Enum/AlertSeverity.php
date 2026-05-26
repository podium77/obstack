<?php
namespace App\Enum;

enum AlertSeverity: string {
    case OK       = 'ok';
    case INFO     = 'info';
    case WARNING  = 'warning';
    case CRITICAL = 'critical';
    case ERROR    = 'error';

    public function getLabel(): string {
        return match($this) {
            self::OK       => 'Normal',
            self::INFO     => 'Information',
            self::WARNING  => 'Avertissement',
            self::CRITICAL => 'Critique',
            self::ERROR    => 'Erreur',
        };
    }
    public function getWeight(): int {
        return match($this) {
            self::OK => 0, self::INFO => 1, self::WARNING => 2,
            self::CRITICAL => 3, self::ERROR => 4,
        };
    }
    public static function fromWeight(int $w): self {
        return match(true) {
            $w >= 4 => self::ERROR, $w >= 3 => self::CRITICAL,
            $w >= 2 => self::WARNING, $w >= 1 => self::INFO, default => self::OK,
        };
    }
}
