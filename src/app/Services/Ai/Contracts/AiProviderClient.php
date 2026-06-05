<?php

namespace App\Services\Ai\Contracts;

use App\Models\AuditLog;
use App\Services\Ai\AiProviderTestResult;

interface AiProviderClient
{
    public function testConnection(): AiProviderTestResult;

    /** @return array<string, mixed> */
    public function analyzeAuditLog(AuditLog $auditLog): array;
}
