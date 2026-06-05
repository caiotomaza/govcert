<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AiProviderSetting;
use App\Services\Ai\AiProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class SystemSettingsController extends Controller
{
    public function index(): View
    {
        $setting = AiProviderSetting::active() ?? AiProviderSetting::latest()->first();

        return view('admin.settings.index', compact('setting'));
    }

    public function updateAiProvider(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => 'required|in:gemini,grok,deepseek,custom',
            'model' => 'nullable|string|max:120',
            'base_url' => 'nullable|url|max:500',
            'timeout_seconds' => 'nullable|integer|min:5|max:120',
        ]);

        // Encontra ou cria o registro para o provedor escolhido
        $setting = AiProviderSetting::firstOrNew(['provider' => $validated['provider']]);
        $setting->model = ($validated['model'] ?? null) ?: null;
        $setting->base_url = ($validated['base_url'] ?? null) ?: null;
        $setting->timeout_seconds = $validated['timeout_seconds'] ?? 30;

        // Atualiza chave somente se uma nova foi enviada (campo não vazio)
        if ($request->filled('api_key')) {
            $setting->api_key_encrypted = encrypt($request->input('api_key'));
        }

        $setting->save();
        $setting->activate();

        ActivityLog::record(
            'settings.ai-provider-updated',
            "Configuração de provedor de IA atualizada: {$setting->provider_label} ({$setting->model}).",
            $setting,
            ['provider' => $setting->provider, 'model' => $setting->model]
            // API key nunca registrada aqui
        );

        return redirect()->route('admin.settings.index')
            ->with('success', 'Configuração de provedor de IA salva com sucesso.');
    }

    public function testAiProvider(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|in:gemini,grok,deepseek,custom',
            'model' => 'nullable|string|max:120',
            'base_url' => 'nullable|url|max:500',
            'timeout_seconds' => 'nullable|integer|min:5|max:120',
        ]);

        // Constrói configuração temporária para o teste (sem persistir)
        $temp = new AiProviderSetting([
            'provider' => $validated['provider'],
            'model' => $validated['model'] ?? null,
            'base_url' => $validated['base_url'] ?? null,
            'timeout_seconds' => $validated['timeout_seconds'] ?? 30,
        ]);

        // Se nova chave foi enviada, usa ela; caso contrário tenta a salva no banco
        if ($request->filled('api_key')) {
            $temp->api_key_encrypted = encrypt($request->input('api_key'));
        } else {
            $saved = AiProviderSetting::where('provider', $validated['provider'])->latest()->first();
            $temp->api_key_encrypted = $saved?->api_key_encrypted;
        }

        $client = AiProviderManager::buildClient($temp);
        $result = $client->testConnection();

        // Atualiza status do último teste no banco, se o registro existir
        AiProviderSetting::where('provider', $validated['provider'])
            ->latest()
            ->first()
            ?->update([
                'last_tested_at' => now(),
                'last_test_status' => $result->success ? 'success' : 'failure',
            ]);

        ActivityLog::record(
            'settings.ai-provider-tested',
            "Teste de API do provedor {$result->provider} ({$result->model}): ".($result->success ? 'sucesso' : 'falha').'.',
            null,
            ['provider' => $result->provider, 'model' => $result->model, 'success' => $result->success]
        );

        return response()->json($result->toArray());
    }

    public function logoutAll(Request $request): RedirectResponse
    {
        // Conta antes de remover
        $sessionCount = DB::table('sessions')->count();
        $tokenCount = PersonalAccessToken::count();

        ActivityLog::record(
            'auth.logout-all',
            'Administrador desconectou todos os usuários do sistema.',
            null,
            [
                'sessions_removed' => $sessionCount,
                'tokens_revoked' => $tokenCount,
                'triggered_by' => $request->user()->email,
            ]
        );

        // Remove todas as sessões web (SESSION_DRIVER=database)
        DB::table('sessions')->delete();

        // Revoga todos os tokens Sanctum
        PersonalAccessToken::query()->delete();

        // A sessão do admin foi removida acima; redireciona para login
        return redirect()->route('login')
            ->with('success', 'Todos os usuários foram desconectados com sucesso.');
    }
}
