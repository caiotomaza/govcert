<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TokenUsageService
{
    /**
     * Retorna o total de tokens consumidos pelo usuário até o momento de $capturedAt,
     * para uso no detalhe de um log específico.
     *
     * Prioriza user_id quando disponível; fallback para user_identifier.
     */
    public function cumulativeTokensUntil(AuditLog $log): int
    {
        $query = AuditLog::query()
            ->where('captured_at', '<=', $log->captured_at);

        if ($log->user_id !== null) {
            $query->where('user_id', $log->user_id);
        } else {
            $query->where('user_identifier', $log->user_identifier);
        }

        return (int) $query->sum('total_tokens');
    }

    /**
     * Retorna ranking de usuários por consumo de tokens com dados enriquecidos
     * para heatmap nas colunas (percentuais relativos ao maior valor).
     *
     * @return Collection<int, object>
     */
    public function rankingByPeriod(Carbon $start, Carbon $end, int $limit = 20): Collection
    {
        $rows = AuditLog::select(
            'user_identifier',
            DB::raw('SUM(total_tokens) as total_tokens'),
            DB::raw('COUNT(*) as log_count'),
            DB::raw('ROUND(AVG(NULLIF(total_tokens, 0)), 0) as avg_tokens')
        )
            ->whereBetween('captured_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->groupBy('user_identifier')
            ->orderByDesc('total_tokens')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return $rows;
        }

        $maxTokens = (int) $rows->max('total_tokens') ?: 1;
        $maxLogs = (int) $rows->max('log_count') ?: 1;

        return $rows->map(function ($row) use ($maxTokens, $maxLogs) {
            $row->token_heat_pct = $maxTokens > 0 ? ($row->total_tokens / $maxTokens) : 0;
            $row->log_heat_pct = $maxLogs > 0 ? ($row->log_count / $maxLogs) : 0;

            return $row;
        });
    }

    /**
     * Retorna estatísticas gerais de tokens no período.
     *
     * @return array{total: int, average: float, max: int, log_count: int}
     */
    public function statsByPeriod(Carbon $start, Carbon $end): array
    {
        $range = [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
        $scoped = AuditLog::whereBetween('captured_at', $range);

        $total = (int) (clone $scoped)->sum('total_tokens');
        $logCount = (int) (clone $scoped)->where('total_tokens', '>', 0)->count();
        $average = $logCount > 0 ? round($total / $logCount, 1) : 0.0;
        $max = (int) (clone $scoped)->max('total_tokens');

        return [
            'total' => $total,
            'average' => $average,
            'max' => $max,
            'log_count' => $logCount,
        ];
    }

    /**
     * Retorna volume de tokens por dia no período (para gráfico de linhas).
     */
    public function tokensByDay(Carbon $start, Carbon $end): Collection
    {
        return AuditLog::select(
            DB::raw('DATE(captured_at) as log_date'),
            DB::raw('SUM(input_tokens) as input_tokens'),
            DB::raw('SUM(output_tokens) as output_tokens'),
            DB::raw('SUM(total_tokens) as total_tokens')
        )
            ->whereBetween('captured_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->groupBy('log_date')
            ->orderBy('log_date')
            ->get();
    }

    /**
     * Calcula o consumo total de tokens de um usuário no período dado.
     *
     * @param  string  $period  daily|weekly|monthly|total
     */
    public function userConsumptionInPeriod(?int $userId, string $userIdentifier, string $period): int
    {
        $query = AuditLog::query();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } else {
            $query->where('user_identifier', $userIdentifier);
        }

        if ($period !== 'total') {
            $start = match ($period) {
                'daily' => Carbon::today()->startOfDay(),
                'weekly' => Carbon::now()->startOfWeek(),
                'monthly' => Carbon::now()->startOfMonth(),
            };
            $query->where('captured_at', '>=', $start);
        }

        return (int) $query->sum('total_tokens');
    }
}
