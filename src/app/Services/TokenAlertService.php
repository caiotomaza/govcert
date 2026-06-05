<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\TokenAlert;
use App\Models\TokenAlertEvent;

class TokenAlertService
{
    public function __construct(private readonly TokenUsageService $usage) {}

    /**
     * Verifica todos os alertas ativos e dispara os que atingiram o limite.
     * Chamado após cada processamento de log ou por scheduler.
     */
    public function checkAll(): void
    {
        TokenAlert::where('is_active', true)->each(function (TokenAlert $alert) {
            $this->checkAlert($alert);
        });
    }

    /**
     * Verifica um alerta específico e registra evento se necessário.
     */
    public function checkAlert(TokenAlert $alert): void
    {
        if ($alert->user_id !== null) {
            $user = $alert->user;
            $identifier = $user?->email ?? '';
            $current = $this->usage->userConsumptionInPeriod($alert->user_id, $identifier, $alert->period);
        } else {
            // Alerta global: soma todos os usuários no período
            $current = $this->globalConsumptionInPeriod($alert->period);
        }

        if ($current < $alert->threshold_tokens) {
            return;
        }

        // Evita disparar o mesmo alerta mais de uma vez por hora
        if ($alert->last_triggered_at && $alert->last_triggered_at->diffInMinutes(now()) < 60) {
            return;
        }

        $targets = $this->buildTargets($alert);

        TokenAlertEvent::create([
            'token_alert_id' => $alert->id,
            'user_id' => $alert->user_id,
            'current_tokens' => $current,
            'threshold_tokens' => $alert->threshold_tokens,
            'triggered_at' => now(),
            'notified_targets' => $targets,
        ]);

        $alert->update(['last_triggered_at' => now()]);

        ActivityLog::record(
            'token_alert.triggered',
            "Alerta de tokens disparado: limite de {$alert->threshold_tokens} tokens ({$alert->period}) atingido com {$current} tokens.",
            $alert,
            ['alert_id' => $alert->id, 'current_tokens' => $current, 'targets' => $targets]
        );
    }

    private function buildTargets(TokenAlert $alert): array
    {
        $targets = [];

        if ($alert->notify_user && $alert->user?->email) {
            $targets[] = $alert->user->email;
        }
        if ($alert->notify_email) {
            $targets[] = $alert->notify_email;
        }

        return $targets;
    }

    private function globalConsumptionInPeriod(string $period): int
    {
        return $this->usage->userConsumptionInPeriod(null, '', $period);
    }
}
