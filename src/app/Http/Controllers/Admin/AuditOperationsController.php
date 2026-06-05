<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAuditLog;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuditOperationsController extends Controller
{
    /**
     * Reenfileira em massa logs com determinado status para reanálise.
     * Usado manualmente pelo admin quando logs ficam presos.
     */
    public function reprocess(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_analysis,failed',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $limit = (int) ($validated['limit'] ?? 200);
        $status = $validated['status'];

        $logs = AuditLog::where('status', $status)
            ->oldest('updated_at')
            ->limit($limit)
            ->get();

        if ($logs->isEmpty()) {
            return back()->with('info', "Nenhum log com status '{$status}' encontrado.");
        }

        foreach ($logs as $log) {
            ProcessAuditLog::dispatch($log->id)->onQueue('gemini');
        }

        ActivityLog::record(
            'audit.reprocess',
            "Admin enfileirou {$logs->count()} log(s) com status '{$status}' para reanálise.",
            null,
            [
                'status' => $status,
                'count' => $logs->count(),
                'triggered_by' => $request->user()?->email,
            ]
        );

        return back()->with('success', "{$logs->count()} log(s) com status '{$status}' enviado(s) para análise.");
    }
}
