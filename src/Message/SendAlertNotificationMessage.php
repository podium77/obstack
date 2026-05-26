<?php
namespace App\Message;

final class SendAlertNotificationMessage
{
    public function __construct(
        public readonly int $alertId,
    ) {}
}
