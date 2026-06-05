@extends('layouts.app')

@section('title', 'Tokens')

@section('content')
<div class="space-y-6">

    {{-- Cabeçalho --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tokens</h1>
            <p class="text-gray-500 text-sm mt-1">Consumo por usuário, ranking e alertas de limite</p>
        </div>
        <div class="flex gap-2">
            <button id="btn-refresh"
                    type="button"
                    class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:border-indigo-400 text-gray-600 hover:text-indigo-600 text-sm px-3 py-2 rounded-lg transition-all duration-300 shadow-sm">
                <svg id="refresh-icon" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Atualizar
            </button>
            <button id="btn-new-alert"
                    type="button"
                    class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded-lg transition-colors">
                + Novo Alerta
            </button>
        </div>
    </div>

    {{-- Filtro de período --}}
    <form method="GET" action="{{ route('tokens.index') }}" class="bg-white rounded-xl shadow p-4">
        <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Data de Início</label>
                <input type="date" name="start_date" value="{{ $startDate }}"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Data de Fim</label>
                <input type="date" name="end_date" value="{{ $endDate }}"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
            </div>
            <div class="flex gap-2">
                <x-button type="submit" variant="primary">Filtrar</x-button>
                <x-button :href="route('tokens.index')" variant="secondary">Últimos 30 dias</x-button>
            </div>
        </div>
    </form>

    {{-- KPI Cards --}}
    <div id="kpi-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-indigo-500">
            <p class="text-sm text-gray-500">Total de Tokens</p>
            <p class="text-3xl font-bold text-indigo-700 mt-1" id="kpi-total">{{ number_format($tokenStats['total']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-indigo-300">
            <p class="text-sm text-gray-500">Média por Log</p>
            <p class="text-3xl font-bold text-indigo-600 mt-1" id="kpi-avg">{{ number_format($tokenStats['average'], 0) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-purple-400">
            <p class="text-sm text-gray-500">Maior Consumo (único log)</p>
            <p class="text-3xl font-bold text-purple-700 mt-1" id="kpi-max">{{ number_format($tokenStats['max']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-yellow-400">
            <p class="text-sm text-gray-500">Alertas Ativos</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $activeAlerts }}</p>
        </div>
    </div>

    {{-- Ranking com heatmap direto nas colunas --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-700">Ranking de Consumo de Tokens</h2>
                <p class="text-xs text-gray-400 mt-0.5">As colunas <em>Total de Tokens</em> e <em>Qtd. de Logs</em> têm intensidade de cor relativa ao maior valor do ranking.</p>
            </div>
            <span class="text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($startDate)->format('d/m/Y') }} — {{ \Illuminate\Support\Carbon::parse($endDate)->format('d/m/Y') }}</span>
        </div>

        @php
            // Função auxiliar de heatmap: retorna classes CSS para fundo e texto
            $heatClasses = function (float $pct): array {
                return match(true) {
                    $pct >= 0.81 => ['bg' => 'bg-red-600',    'text' => 'text-white',       'label' => 'Crítico'],
                    $pct >= 0.61 => ['bg' => 'bg-orange-400', 'text' => 'text-white',       'label' => 'Alto'],
                    $pct >= 0.41 => ['bg' => 'bg-yellow-300', 'text' => 'text-yellow-900',  'label' => 'Médio'],
                    $pct >= 0.21 => ['bg' => 'bg-indigo-200', 'text' => 'text-indigo-900',  'label' => 'Baixo'],
                    default      => ['bg' => 'bg-gray-100',   'text' => 'text-gray-600',    'label' => 'Mínimo'],
                };
            };
        @endphp

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="ranking-table">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-center w-10">#</th>
                        <th class="px-4 py-3">Usuário</th>
                        <th class="px-4 py-3 text-right">
                            Total de Tokens
                            <span class="ml-1 text-indigo-400" title="Intensidade relativa ao maior consumo do período">🌡</span>
                        </th>
                        <th class="px-4 py-3 text-right">
                            Qtd. de Logs
                            <span class="ml-1 text-indigo-400" title="Intensidade relativa à maior quantidade de logs">🌡</span>
                        </th>
                        <th class="px-4 py-3 text-right">Média/Log</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="ranking-body">
                    @forelse($ranking as $i => $row)
                    @php
                        $tokenHeat = $heatClasses((float) ($row->token_heat_pct ?? 0));
                        $logHeat   = $heatClasses((float) ($row->log_heat_pct ?? 0));
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 text-center font-mono text-gray-400 text-xs">{{ $i + 1 }}</td>

                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900 text-sm">{{ $row->user_identifier }}</div>
                        </td>

                        {{-- Heatmap: Total de Tokens --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <span class="font-mono font-semibold text-gray-800">{{ number_format($row->total_tokens) }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $tokenHeat['bg'] }} {{ $tokenHeat['text'] }}"
                                      title="Intensidade de consumo de tokens: {{ $tokenHeat['label'] }}">
                                    {{ $tokenHeat['label'] }}
                                </span>
                            </div>
                            <div class="mt-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-1.5 rounded-full {{ $tokenHeat['bg'] }}"
                                     style="width: {{ round(($row->token_heat_pct ?? 0) * 100) }}%"></div>
                            </div>
                        </td>

                        {{-- Heatmap: Qtd. de Logs --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <span class="font-mono font-semibold text-gray-800">{{ number_format($row->log_count) }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $logHeat['bg'] }} {{ $logHeat['text'] }}"
                                      title="Intensidade de volume de logs: {{ $logHeat['label'] }}">
                                    {{ $logHeat['label'] }}
                                </span>
                            </div>
                            <div class="mt-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-1.5 rounded-full {{ $logHeat['bg'] }}"
                                     style="width: {{ round(($row->log_heat_pct ?? 0) * 100) }}%"></div>
                            </div>
                        </td>

                        <td class="px-4 py-3 text-right font-mono text-gray-500 text-xs">
                            {{ $row->avg_tokens ? number_format((int) $row->avg_tokens) : '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                            Nenhum dado de consumo no período selecionado.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Legenda das cores --}}
        <div class="px-6 py-3 border-t border-gray-50 flex items-center gap-4 text-xs text-gray-500">
            <span class="font-medium">Intensidade:</span>
            @foreach(['bg-gray-100 text-gray-600' => 'Mínimo', 'bg-indigo-200 text-indigo-900' => 'Baixo', 'bg-yellow-300 text-yellow-900' => 'Médio', 'bg-orange-400 text-white' => 'Alto', 'bg-red-600 text-white' => 'Crítico'] as $cls => $lbl)
                <span class="inline-flex items-center gap-1">
                    <span class="w-3 h-3 rounded {{ explode(' ', $cls)[0] }} inline-block border border-gray-200"></span>
                    {{ $lbl }}
                </span>
            @endforeach
        </div>
    </div>

    {{-- Lista de alertas --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-700">Alertas de Limite de Tokens</h2>
        </div>

        @if($alerts->isEmpty())
        <div class="px-6 py-10 text-center text-gray-400 text-sm">
            Nenhum alerta configurado. Clique em "Novo Alerta" para criar.
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-4 py-3">Usuário Monitorado</th>
                        <th class="px-4 py-3 text-right">Limite</th>
                        <th class="px-4 py-3">Período</th>
                        <th class="px-4 py-3">Notificar</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Último Disparo</th>
                        <th class="px-4 py-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($alerts as $alert)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $alert->user?->email ?? '(todos os usuários)' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-indigo-700 font-semibold">
                            {{ number_format($alert->threshold_tokens) }}
                        </td>
                        <td class="px-4 py-3">{{ $alert->period_label }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            @if($alert->notify_user) <span class="inline-block bg-blue-50 text-blue-700 border border-blue-200 rounded px-1.5 py-0.5 mr-1">Usuário</span>@endif
                            @if($alert->notify_auditors) <span class="inline-block bg-indigo-50 text-indigo-700 border border-indigo-200 rounded px-1.5 py-0.5 mr-1">Auditores</span>@endif
                            @if($alert->notify_admins) <span class="inline-block bg-purple-50 text-purple-700 border border-purple-200 rounded px-1.5 py-0.5 mr-1">Admins</span>@endif
                            @if($alert->notify_email) <span class="inline-block bg-gray-100 text-gray-700 rounded px-1.5 py-0.5 truncate max-w-[120px]" title="{{ $alert->notify_email }}">{{ $alert->notify_email }}</span>@endif
                        </td>
                        <td class="px-4 py-3">
                            @if($alert->is_active)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Ativo
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                    Inativo
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                            {{ $alert->last_triggered_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <button type="button"
                                        class="btn-edit-alert text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-2 py-1 rounded transition-colors"
                                        data-alert="{{ json_encode(['id' => $alert->id, 'user_id' => $alert->user_id, 'threshold_tokens' => $alert->threshold_tokens, 'period' => $alert->period, 'notify_user' => $alert->notify_user, 'notify_auditors' => $alert->notify_auditors, 'notify_admins' => $alert->notify_admins, 'notify_email' => $alert->notify_email, 'is_active' => $alert->is_active]) }}">
                                    Editar
                                </button>
                                <form method="POST" action="{{ route('tokens.alerts.toggle', $alert) }}">
                                    @csrf
                                    <button type="submit"
                                            class="text-xs {{ $alert->is_active ? 'bg-yellow-50 hover:bg-yellow-100 text-yellow-700' : 'bg-green-50 hover:bg-green-100 text-green-700' }} px-2 py-1 rounded transition-colors">
                                        {{ $alert->is_active ? 'Desativar' : 'Ativar' }}
                                    </button>
                                </form>
                                @if(Auth::user()->isAdmin())
                                <form method="POST" action="{{ route('tokens.alerts.destroy', $alert) }}"
                                      onsubmit="return confirm('Excluir este alerta?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="text-xs bg-red-50 hover:bg-red-100 text-red-700 px-2 py-1 rounded transition-colors">
                                        Excluir
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

{{-- Modal de criação/edição de alerta --}}
<x-modal id="alert-modal" max-width="max-w-lg">
    <div class="flex items-center justify-between px-5 py-4 bg-indigo-800 text-white">
        <h3 id="alert-modal-title" class="font-semibold">Novo Alerta de Tokens</h3>
        <button id="alert-close" type="button"
            class="text-indigo-200 hover:text-white hover:bg-indigo-700 rounded-lg w-8 h-8 flex items-center justify-center text-xl leading-none transition-all duration-300">&times;</button>
    </div>

    <form id="alert-form" method="POST" action="{{ route('tokens.alerts.store') }}" class="p-5 space-y-4">
        @csrf
        <input type="hidden" name="_method" id="alert-method" value="POST">
        <input type="hidden" name="alert_id" id="alert-id">

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Usuário Monitorado</label>
            <select name="user_id" id="alert-user-id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
                <option value="">Todos os usuários (global)</option>
                @foreach(\App\Models\User::orderBy('email')->get() as $u)
                    <option value="{{ $u->id }}">{{ $u->email }} ({{ $u->isAdmin() ? 'Admin' : 'Auditor' }})</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Limite de Tokens *</label>
                <input type="number" name="threshold_tokens" id="alert-threshold" min="1"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400"
                       placeholder="Ex: 50000" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Período *</label>
                <select name="period" id="alert-period"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
                    <option value="daily">Diário</option>
                    <option value="weekly">Semanal</option>
                    <option value="monthly" selected>Mensal</option>
                    <option value="total">Total acumulado</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-2">Quem notificar</label>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="notify_user" id="alert-notify-user" value="1"
                           class="w-4 h-4 text-indigo-600 rounded">
                    Próprio usuário
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="notify_auditors" id="alert-notify-auditors" value="1" checked
                           class="w-4 h-4 text-indigo-600 rounded">
                    Auditores
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="notify_admins" id="alert-notify-admins" value="1"
                           class="w-4 h-4 text-indigo-600 rounded">
                    Administradores
                </label>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">E-mail adicional (opcional)</label>
            <input type="email" name="notify_email" id="alert-notify-email"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400"
                   placeholder="outro@orgao.gov.br">
        </div>

        <div id="alert-active-row" class="hidden">
            <label class="flex items-center gap-2 cursor-pointer text-sm">
                <input type="checkbox" name="is_active" id="alert-is-active" value="1" checked
                       class="w-4 h-4 text-indigo-600 rounded">
                Alerta ativo
            </label>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" id="alert-cancel"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded-lg transition-colors">
                Salvar
            </button>
        </div>
    </form>
</x-modal>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Refresh AJAX ─────────────────────────────────────────────────────────
    const btnRefresh  = document.getElementById('btn-refresh');
    const refreshIcon = document.getElementById('refresh-icon');

    btnRefresh.addEventListener('click', async function () {
        btnRefresh.disabled = true;
        refreshIcon.classList.add('animate-spin');
        try {
            const res = await fetch(window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            // Recarrega para simplificar (o server-side render é leve)
            window.location.reload();
        } catch (err) {
            console.error('[GovCert] Erro ao atualizar:', err);
        } finally {
            refreshIcon.classList.remove('animate-spin');
            btnRefresh.disabled = false;
        }
    });

    // ── Modal de alerta ──────────────────────────────────────────────────────
    const modal      = document.getElementById('alert-modal');
    const form       = document.getElementById('alert-form');
    const title      = document.getElementById('alert-modal-title');
    const methodField = document.getElementById('alert-method');
    const idField    = document.getElementById('alert-id');

    const STORE_URL  = '{{ route("tokens.alerts.store") }}';
    const UPDATE_BASE = '{{ url("tokens/alerts") }}';

    function openNew() {
        title.textContent = 'Novo Alerta de Tokens';
        form.action = STORE_URL;
        methodField.value = 'POST';
        idField.value = '';
        form.reset();
        document.getElementById('alert-active-row').classList.add('hidden');
        document.getElementById('alert-notify-auditors').checked = true;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function openEdit(data) {
        title.textContent = 'Editar Alerta';
        form.action = UPDATE_BASE + '/' + data.id;
        methodField.value = 'PUT';
        idField.value = data.id;

        document.getElementById('alert-user-id').value         = data.user_id || '';
        document.getElementById('alert-threshold').value       = data.threshold_tokens;
        document.getElementById('alert-period').value          = data.period;
        document.getElementById('alert-notify-user').checked   = !!data.notify_user;
        document.getElementById('alert-notify-auditors').checked = !!data.notify_auditors;
        document.getElementById('alert-notify-admins').checked = !!data.notify_admins;
        document.getElementById('alert-notify-email').value    = data.notify_email || '';
        document.getElementById('alert-is-active').checked     = !!data.is_active;
        document.getElementById('alert-active-row').classList.remove('hidden');

        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    document.getElementById('btn-new-alert').addEventListener('click', openNew);
    document.getElementById('alert-close').addEventListener('click', closeModal);
    document.getElementById('alert-cancel').addEventListener('click', closeModal);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });

    document.querySelectorAll('.btn-edit-alert').forEach(btn => {
        btn.addEventListener('click', function () {
            openEdit(JSON.parse(this.dataset.alert));
        });
    });

    // Backdrop close
    modal.querySelector('[data-close]')?.addEventListener('click', closeModal);
})();
</script>
@endpush
