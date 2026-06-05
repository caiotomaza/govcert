<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\TokenAlert;
use App\Services\TokenUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TokenGovernanceController extends Controller
{
    public function __construct(private readonly TokenUsageService $tokenUsage) {}

    public function index(Request $request): View|JsonResponse
    {
        $start = $this->parseDate($request->input('start_date'), Carbon::now()->subDays(30));
        $end = $this->parseDate($request->input('end_date'), Carbon::now());

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $tokenStats = $this->tokenUsage->statsByPeriod($start, $end);
        $ranking = $this->tokenUsage->rankingByPeriod($start, $end, 20);
        $tokensByDay = $this->tokenUsage->tokensByDay($start, $end);
        $alerts = TokenAlert::with(['user', 'createdBy'])->latest()->get();
        $activeAlerts = $alerts->where('is_active', true)->count();

        if ($request->ajax()) {
            return response()->json([
                'tokenStats' => $tokenStats,
                'ranking' => $ranking,
                'tokensByDay' => $tokensByDay,
            ]);
        }

        return view('tokens.index', [
            'tokenStats' => $tokenStats,
            'ranking' => $ranking,
            'tokensByDay' => $tokensByDay,
            'alerts' => $alerts,
            'activeAlerts' => $activeAlerts,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
        ]);
    }

    public function storeAlert(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'threshold_tokens' => 'required|integer|min:1',
            'period' => 'required|in:daily,weekly,monthly,total',
            'notify_user' => 'boolean',
            'notify_auditors' => 'boolean',
            'notify_admins' => 'boolean',
            'notify_email' => 'nullable|email|max:255',
        ]);

        $alert = TokenAlert::create([
            ...$validated,
            'notify_user' => (bool) ($validated['notify_user'] ?? false),
            'notify_auditors' => (bool) ($validated['notify_auditors'] ?? true),
            'notify_admins' => (bool) ($validated['notify_admins'] ?? false),
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        ActivityLog::record(
            'token_alert.created',
            "Alerta de tokens criado: limite de {$alert->threshold_tokens} tokens ({$alert->period}).",
            $alert,
        );

        return redirect()->route('tokens.index')
            ->with('success', 'Alerta de tokens criado com sucesso.');
    }

    public function updateAlert(Request $request, TokenAlert $alert): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'threshold_tokens' => 'required|integer|min:1',
            'period' => 'required|in:daily,weekly,monthly,total',
            'notify_user' => 'boolean',
            'notify_auditors' => 'boolean',
            'notify_admins' => 'boolean',
            'notify_email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
        ]);

        $alert->update([
            ...$validated,
            'notify_user' => (bool) ($validated['notify_user'] ?? false),
            'notify_auditors' => (bool) ($validated['notify_auditors'] ?? false),
            'notify_admins' => (bool) ($validated['notify_admins'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        ActivityLog::record(
            'token_alert.updated',
            "Alerta de tokens #{$alert->id} atualizado.",
            $alert,
        );

        return redirect()->route('tokens.index')
            ->with('success', 'Alerta atualizado com sucesso.');
    }

    public function toggleAlert(TokenAlert $alert): RedirectResponse
    {
        $alert->update(['is_active' => ! $alert->is_active]);

        $action = $alert->is_active ? 'ativado' : 'desativado';
        ActivityLog::record(
            'token_alert.'.($alert->is_active ? 'activated' : 'deactivated'),
            "Alerta de tokens #{$alert->id} {$action}.",
            $alert,
        );

        return redirect()->route('tokens.index')
            ->with('success', "Alerta {$action} com sucesso.");
    }

    public function destroyAlert(TokenAlert $alert): RedirectResponse
    {
        ActivityLog::record(
            'token_alert.deleted',
            "Alerta de tokens #{$alert->id} excluído (limite: {$alert->threshold_tokens} tokens, período: {$alert->period}).",
            null,
        );

        $alert->delete();

        return redirect()->route('tokens.index')
            ->with('success', 'Alerta excluído.');
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
