<?php

namespace App\Message;

final class CollectMetricsMessage
{
    public function __construct(
        public readonly ?int $applicationId = null, // null = toutes les apps
    ) {}
}
