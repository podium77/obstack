<?php
namespace App\Message;

final class CheckAlertsMessage
{
    public function __construct(
        public readonly ?int $applicationId = null,
    ) {}
}
