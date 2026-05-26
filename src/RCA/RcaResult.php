<?php
namespace App\RCA;

class RcaResult
{
    public function __construct(
        public readonly bool               $success,
        public readonly array              $rootCauses  = [],
        public readonly ?float             $confidence  = null,
        public readonly ?string            $explanation = null,
        public readonly ?array             $graphData   = null,
        public readonly ?string            $model       = null,
        public readonly ?string            $error       = null,
        public readonly ?\DateTimeImmutable $analyzedAt  = null,
        public readonly bool               $disabled    = false,
    ) {}

    public static function disabled(): self
    {
        return new self(success: false, disabled: true);
    }

    public static function error(string $message): self
    {
        return new self(success: false, error: $message);
    }

    public function getTopRootCause(): ?array
    {
        return $this->rootCauses[0] ?? null;
    }

    public function toArray(): array
    {
        return [
            'success'     => $this->success,
            'root_causes' => $this->rootCauses,
            'confidence'  => $this->confidence,
            'explanation' => $this->explanation,
            'graph_data'  => $this->graphData,
            'model'       => $this->model,
            'error'       => $this->error,
            'analyzed_at' => $this->analyzedAt?->format('c'),
        ];
    }
}
