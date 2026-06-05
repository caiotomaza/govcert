@extends('layouts.app')

@section('title', 'Configurações do GovCert')

@section('content')
<div class="max-w-3xl space-y-8">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">Configurações do GovCert</h1>
        <p class="text-gray-500 text-sm mt-1">Gerenciamento do sistema, provedor de IA e sessões</p>
    </div>

    {{-- ── Provedor de IA ──────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow p-6 space-y-6">
        <div>
            <h2 class="text-base font-semibold text-gray-800">Provedor de IA para Análise</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Define qual serviço de IA processa os logs capturados.
                @if($setting && $setting->is_active)
                    <span class="ml-1 inline-flex items-center gap-1 text-green-700 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                        Ativo: {{ $setting->provider_label }}
                        @if($setting->model) ({{ $setting->model }}) @endif
                    </span>
                @else
                    <span class="ml-1 text-yellow-600">Usando configuração padrão do ambiente (.env)</span>
                @endif
            </p>
        </div>

        <form id="form-ai-provider" method="POST" action="{{ route('admin.settings.ai-provider.update') }}" class="space-y-4">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Provedor *</label>
                    <select name="provider" id="sel-provider"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
                        @foreach(['gemini' => 'Google Gemini', 'grok' => 'Grok (xAI)', 'deepseek' => 'DeepSeek', 'custom' => 'Customizado (OpenAI-compatível)'] as $val => $label)
                            <option value="{{ $val }}" {{ ($setting?->provider ?? 'gemini') === $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('provider')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Modelo</label>
                    <input type="text" name="model" id="inp-model"
                        value="{{ old('model', $setting?->model) }}"
                        placeholder="ex: gemini-2.5-flash, grok-beta, deepseek-chat"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
                    @error('model')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Base URL — visível apenas para grok, deepseek, custom --}}
            <div id="row-base-url" class="{{ in_array($setting?->provider, ['grok','deepseek','custom']) ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-gray-700 mb-1">Base URL / Endpoint</label>
                <input type="url" name="base_url"
                    value="{{ old('base_url', $setting?->base_url) }}"
                    placeholder="https://api.exemplo.com/v1"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
                @error('base_url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chave de API</label>
                    @if($setting?->api_key_encrypted)
                        <p class="text-xs text-gray-500 mb-1">
                            Configurada: <code class="bg-gray-100 px-1 rounded text-gray-700">{{ $setting->maskedApiKey() }}</code>
                            — deixe em branco para manter.
                        </p>
                    @endif
                    <input type="password" name="api_key" autocomplete="new-password"
                        placeholder="{{ $setting?->api_key_encrypted ? 'Nova chave (deixe vazio para manter)' : 'Cole a chave da API aqui' }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
                    <p class="mt-1 text-xs text-gray-400">A chave é armazenada criptografada e nunca exibida completa.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Timeout (segundos)</label>
                    <input type="number" name="timeout_seconds" min="5" max="120"
                        value="{{ old('timeout_seconds', $setting?->timeout_seconds ?? 30) }}"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
                    @error('timeout_seconds')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Última testagem --}}
            @if($setting?->last_tested_at)
            <p class="text-xs text-gray-400">
                Último teste: {{ $setting->last_tested_at->format('d/m/Y H:i') }}
                —
                @if($setting->last_test_status === 'success')
                    <span class="text-green-600 font-medium">Sucesso</span>
                @else
                    <span class="text-red-600 font-medium">Falhou</span>
                @endif
            </p>
            @endif

            <div class="flex items-center gap-3 pt-2">
                <x-button type="submit" variant="primary">Salvar Configuração</x-button>
                <x-button type="button" id="btn-test-api" variant="soft-indigo">Testar API</x-button>
            </div>
        </form>

        {{-- Resultado do teste de API --}}
        <div id="test-result" class="hidden rounded-lg border p-4 text-sm space-y-2"></div>
    </div>

    {{-- ── Reprocessamento de Logs ────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Reprocessamento de Logs de Auditoria</h2>
        <p class="text-sm text-gray-500 mb-4">
            Reenfileira logs que ficaram travados ou falharam para uma nova análise de IA.
            Use quando o worker estiver ativo mas logs permanecerem em <code class="bg-gray-100 px-1 rounded">pending</code>,
            <code class="bg-gray-100 px-1 rounded">in_analysis</code> ou <code class="bg-gray-100 px-1 rounded">failed</code>.
        </p>

        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('admin.audit.reprocess') }}">
                @csrf
                <input type="hidden" name="status" value="pending">
                <x-button type="submit" variant="soft-indigo">Reenfileirar Pendentes</x-button>
            </form>
            <form method="POST" action="{{ route('admin.audit.reprocess') }}">
                @csrf
                <input type="hidden" name="status" value="in_analysis">
                <x-button type="submit" variant="soft-indigo">Recuperar Em Análise</x-button>
            </form>
            <form method="POST" action="{{ route('admin.audit.reprocess') }}">
                @csrf
                <input type="hidden" name="status" value="failed">
                <x-button type="submit" variant="soft-indigo">Reprocessar Falhos</x-button>
            </form>
        </div>

        @if(session('info'))
            <p class="mt-3 text-sm text-blue-700">{{ session('info') }}</p>
        @endif
    </div>

    {{-- ── Segurança / Sessões ─────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Gerenciamento de Sessões</h2>
        <p class="text-sm text-gray-500 mb-4">
            Desconecta imediatamente todos os usuários do painel web e revoga tokens da extensão Chrome.
            <strong class="text-red-600">O administrador também será desconectado.</strong>
        </p>

        <x-button id="btn-logout-all" type="button" variant="danger">
            Desconectar todos os usuários
        </x-button>
    </div>

</div>

{{-- Modal de confirmação: Logout all --}}
<x-modal id="logout-all-modal" max-width="max-w-md">
    <div class="flex items-center justify-between px-5 py-4 bg-red-700 text-white">
        <h3 class="font-semibold">Confirmar desconexão em massa</h3>
        <button id="logout-all-close" type="button"
            class="text-red-200 hover:text-white hover:bg-red-600 rounded-lg w-8 h-8 flex items-center justify-center text-xl leading-none">&times;</button>
    </div>
    <div class="p-5 space-y-4">
        <p class="text-sm text-gray-700">
            Tem certeza que deseja desconectar <strong>todos os usuários</strong>?
            Todos precisarão fazer login novamente — inclusive você.
        </p>
        <p class="text-sm text-red-600 font-medium">Esta ação não pode ser desfeita.</p>
        <div class="flex gap-3 pt-2">
            <form method="POST" action="{{ route('admin.settings.logout-all') }}">
                @csrf
                <x-button type="submit" variant="danger">Sim, desconectar todos</x-button>
            </form>
            <x-button type="button" variant="secondary" data-close>Cancelar</x-button>
        </div>
    </div>
</x-modal>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // ── Mostrar/ocultar Base URL conforme provedor ────────────────────────
    const selProvider = document.getElementById('sel-provider');
    const rowBaseUrl  = document.getElementById('row-base-url');
    const inpModel    = document.getElementById('inp-model');

    const defaultModels = {
        gemini:   'gemini-2.5-flash',
        grok:     'grok-beta',
        deepseek: 'deepseek-chat',
        custom:   '',
    };

    selProvider.addEventListener('change', function () {
        const needsUrl = ['grok', 'deepseek', 'custom'].includes(this.value);
        rowBaseUrl.classList.toggle('hidden', !needsUrl);
        if (!inpModel.value) {
            inpModel.placeholder = 'ex: ' + (defaultModels[this.value] || 'modelo-do-provedor');
        }
    });

    // ── Teste de API ──────────────────────────────────────────────────────
    const btnTest   = document.getElementById('btn-test-api');
    const resultBox = document.getElementById('test-result');
    const form      = document.getElementById('form-ai-provider');

    btnTest.addEventListener('click', async function () {
        btnTest.disabled = true;
        btnTest.textContent = 'Testando...';
        resultBox.classList.add('hidden');

        const fd = new FormData(form);
        const payload = {
            provider:        fd.get('provider'),
            model:           fd.get('model') || null,
            base_url:        fd.get('base_url') || null,
            api_key:         fd.get('api_key') || null,
            timeout_seconds: fd.get('timeout_seconds') || null,
        };

        try {
            const res = await fetch('{{ route("admin.settings.ai-provider.test") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            const d = await res.json();
            showTestResult(d);
        } catch {
            showTestResult({ success: false, error_message: 'Falha de comunicação com o servidor.' });
        } finally {
            btnTest.disabled = false;
            btnTest.textContent = 'Testar API';
        }
    });

    function showTestResult(d) {
        const ok     = !!d.success;
        const border = ok ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50';
        const icon   = ok
            ? '<span class="text-green-600 font-bold text-base">✓ Conexão bem-sucedida</span>'
            : '<span class="text-red-600 font-bold text-base">✗ Falha na conexão</span>';

        resultBox.className = `rounded-lg border p-4 text-sm space-y-1 ${border}`;
        resultBox.innerHTML = `
            <div>${icon}</div>
            <div class="text-gray-700"><strong>Provedor:</strong> ${esc(d.provider || '—')}</div>
            <div class="text-gray-700"><strong>Modelo:</strong> ${esc(d.model || '—')}</div>
            <div class="text-gray-700"><strong>HTTP:</strong> ${esc(String(d.http_status || '—'))}</div>
            <div class="text-gray-700"><strong>Tempo de resposta:</strong> ${d.response_time_ms != null ? Math.round(d.response_time_ms) + ' ms' : '—'}</div>
            ${d.response_snippet ? `<div class="text-gray-700"><strong>Resposta:</strong> <code class="bg-white rounded px-1">${esc(d.response_snippet)}</code></div>` : ''}
            ${d.error_message ? `<div class="text-red-600"><strong>Erro:</strong> ${esc(d.error_message)}</div>` : ''}
            <div class="text-gray-400 text-xs">Testado em: ${esc(d.tested_at || '')}</div>
        `;
        resultBox.classList.remove('hidden');
    }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Modal: Logout All ─────────────────────────────────────────────────
    const modal     = document.getElementById('logout-all-modal');
    const btnLogout = document.getElementById('btn-logout-all');
    const closeBtn  = document.getElementById('logout-all-close');

    function openModal()  { modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); }
    function closeModal() { modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }

    btnLogout.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    modal.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', closeModal));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
})();
</script>
@endpush
