<?php
namespace App\Message;

use App\Enum\RemediationAction;

final class RemediationJobMessage
{
    public function __construct(
        public readonly int               $applicationId,
        public readonly RemediationAction $action,
        public readonly ?int              $policyId    = null,
        public readonly ?string           $triggeredBy = null,
        public readonly array             $extraParams = [],
    ) {}
}
