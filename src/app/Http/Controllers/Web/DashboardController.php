<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\TokenUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly TokenUsageService $tokenUsage) {}

    public function index(Request $request): View|JsonResponse
    {
        $start = $this->parseDate($request->input('start_date'), Carbon::now()->subDays(30));
        $end = $this->parseDate($request->input('end_date'), Carbon::now());

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $range = [$start->copy()->startOfDay(), $end->copy()->endOfDay()];

        $scoped = fn () => AuditLog::whereBetween('created_at', $range);

        $stats = [
            'total' => $scoped()->count(),
            'pending' => $scoped()->whereIn('status', ['pending', 'in_analysis'])->count(),
            'sensitive' => $scoped()->where('has_sensitive_data', true)->count(),
            'critical' => $scoped()->where('risk_level', 'critical')->count(),
        ];

        $tokenStats = $this->tokenUsage->statsByPeriod($start, $end);

        $volumeByDay = AuditLog::select(
            DB::raw('DATE(created_at) as log_date'),
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(has_sensitive_data) as sensitive_count')
        )
            ->whereBetween('created_at', $range)
            ->groupBy('log_date')
            ->orderBy('log_date')
            ->get();

        $tokensByDay = $this->tokenUsage->tokensByDay($start, $end);

        $riskDistribution = AuditLog::select('risk_level', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', $range)
            ->whereNotNull('risk_level')
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level');

        $byUser = AuditLog::select(
            'user_identifier',
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(has_sensitive_data) as sensitive_count')
        )
            ->whereBetween('created_at', $range)
            ->where('has_sensitive_data', true)
            ->groupBy('user_identifier')
            ->orderByDesc('sensitive_count')
            ->limit(10)
            ->get();

        $topTokenUsers = $this->tokenUsage->rankingByPeriod($start, $end, 10);

        if ($request->ajax()) {
            return response()->json([
                'stats' => $stats,
                'tokenStats' => $tokenStats,
                'volumeByDay' => $volumeByDay,
                'tokensByDay' => $tokensByDay,
                'riskDistribution' => $riskDistribution,
                'byUser' => $byUser,
                'topTokenUsers' => $topTokenUsers,
            ]);
        }

        return view('dashboard.index', [
            'stats' => $stats,
            'tokenStats' => $tokenStats,
            'volumeByDay' => $volumeByDay,
            'tokensByDay' => $tokensByDay,
            'riskDistribution' => $riskDistribution,
            'byUser' => $byUser,
            'topTokenUsers' => $topTokenUsers,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
        ]);
    }

    private function parseDate(?string $value, Carbon $default): Carbon
    {
        if (! $value) {
            return $default;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return $default;
        }
    }
}
