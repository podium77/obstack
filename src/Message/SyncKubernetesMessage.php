<?php

namespace App\Message;

final class SyncKubernetesMessage
{
    public function __construct(
        public readonly int $environmentId,
    ) {}
}
