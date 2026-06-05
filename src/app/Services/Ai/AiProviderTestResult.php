<?php

namespace App\Services\Ai;

class AiProviderTestResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $httpStatus,
        public readonly float $responseTimeMs,
        public readonly string $responseSnippet,
        public readonly ?string $errorMessage,
        public readonly string $testedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'provider' => $this->provider,
            'model' => $this->model,
            'http_status' => $this->httpStatus,
            'response_time_ms' => round($this->responseTimeMs),
            'response_snippet' => $this->responseSnippet,
            'error_message' => $this->errorMessage,
            'tested_at' => $this->testedAt,
        ];
    }
}
