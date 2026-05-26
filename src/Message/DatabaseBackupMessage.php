<?php
namespace App\Message;

final class DatabaseBackupMessage
{
    public function __construct(
        public readonly ?int $applicationId = null,
    ) {}
}
