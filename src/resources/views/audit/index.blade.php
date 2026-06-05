@extends('layouts.app')

@section('title', 'Auditoria')

@section('content')
<div class="space-y-6">

    {{-- Cabeçalho --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Auditoria</h1>
            <p class="text-gray-500 text-sm mt-1">Registros de interações capturados pela extensão Chrome</p>
        </div>
        <div class="flex items-center gap-2">
            <button id="btn-refresh"
                    type="button"
                    title="Atualizar tabela"
                    class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:border-indigo-400 text-gray-600 hover:text-indigo-600 text-sm px-3 py-2 rounded-lg transition-all duration-300 shadow-sm">
                <svg id="refresh-icon" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Atualizar
            </button>

            <a href="{{ route('audit.export.csv', request()->query()) }}"
               class="inline-flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white text-sm px-4 py-2 rounded-lg transition-colors">
                Exportar CSV
            </a>
            <button id="btn-export-pdf"
               class="inline-flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2 rounded-lg transition-colors">
                Exportar PDF
            </button>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('audit.index') }}" class="bg-white rounded-xl shadow p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Data Início</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Data Fim</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Usuário</label>
                <input type="text" name="user_identifier" value="{{ request('user_identifier') }}"
                    placeholder="Identificador do usuário"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Nível de Risco</label>
                <select name="risk_level" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
                    <option value="">Todos</option>
                    <option value="low"      {{ request('risk_level') === 'low'      ? 'selected' : '' }}>Baixo</option>
                    <option value="medium"   {{ request('risk_level') === 'medium'   ? 'selected' : '' }}>Médio</option>
                    <option value="high"     {{ request('risk_level') === 'high'     ? 'selected' : '' }}>Alto</option>
                    <option value="critical" {{ request('risk_level') === 'critical' ? 'selected' : '' }}>Crítico</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400">
                    <option value="">Todos</option>
                    <option value="pending"     {{ request('status') === 'pending'     ? 'selected' : '' }}>Pendente</option>
                    <option value="in_analysis" {{ request('status') === 'in_analysis' ? 'selected' : '' }}>Em Análise</option>
                    <option value="completed"   {{ request('status') === 'completed'   ? 'selected' : '' }}>Concluído</option>
                    <option value="failed"      {{ request('status') === 'failed'      ? 'selected' : '' }}>Falha</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2 mt-4">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded-lg">Filtrar</button>
            <a href="{{ route('audit.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm px-4 py-2 rounded-lg">Limpar</a>
        </div>
    </form>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div id="audit-table-container" class="transition-opacity duration-200">
            @include('audit._table', ['logs' => $logs])
        </div>
    </div>

</div>

{{-- Modal de chat — dados sempre mascarados, sem opção de revelar original --}}
<x-modal id="chat-modal" max-width="max-w-2xl" panel-class="bg-gray-50">
    <div class="flex items-center justify-between px-5 py-4 bg-indigo-800 text-white">
        <div class="flex-1 min-w-0">
            <h3 class="font-semibold">
                Interação Auditada
                <span id="m-id" class="text-indigo-300 text-sm font-mono"></span>
            </h3>
            <p class="text-xs text-indigo-200 truncate" id="m-user"></p>
        </div>
        <button id="chat-close" type="button"
            class="text-indigo-200 hover:text-white hover:bg-indigo-700 rounded-lg w-8 h-8 flex items-center justify-center text-xl leading-none transition-all duration-300 ml-3 flex-shrink-0"
            aria-label="Fechar">&times;</button>
    </div>

    {{-- Loading --}}
    <div id="m-loading" class="flex items-center justify-center py-16">
        <svg class="animate-spin h-6 w-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 12 0 12 0v4a8 8 0 00-8 8H4z"></path>
        </svg>
    </div>

    {{-- Conteúdo --}}
    <div id="m-content" class="hidden flex-1 overflow-y-auto p-5 space-y-4">

        {{-- Aviso permanente de mascaramento --}}
        <div class="flex items-start gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span>Dados sensíveis estão permanentemente ocultos nesta visualização. O conteúdo original é preservado somente no banco de dados para fins de auditoria forense.</span>
        </div>

        {{-- Conversa --}}
        <div class="flex justify-end">
            <div class="max-w-[80%] bg-indigo-600 text-white rounded-2xl rounded-br-sm px-4 py-3">
                <p class="text-[10px] uppercase tracking-wide text-indigo-200 mb-1">Usuário</p>
                <p id="m-input" class="text-sm whitespace-pre-wrap break-words"></p>
            </div>
        </div>
        <div class="flex justify-start">
            <div class="max-w-[80%] bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-bl-sm px-4 py-3 shadow-sm">
                <p id="m-agent" class="text-xs font-semibold text-indigo-700 mb-1"></p>
                <p id="m-output" class="text-sm whitespace-pre-wrap break-words"></p>
            </div>
        </div>

        {{-- Tokens deste log --}}
        <div id="m-tokens-section" class="grid grid-cols-3 gap-2">
            <div class="bg-indigo-50 rounded-lg p-3 text-center border border-indigo-100">
                <p class="text-xs text-indigo-500 mb-0.5">Tokens de Entrada</p>
                <p id="m-input-tokens" class="text-lg font-bold text-indigo-800">—</p>
            </div>
            <div class="bg-indigo-50 rounded-lg p-3 text-center border border-indigo-100">
                <p class="text-xs text-indigo-500 mb-0.5">Tokens de Saída</p>
                <p id="m-output-tokens" class="text-lg font-bold text-indigo-800">—</p>
            </div>
            <div class="bg-indigo-100 rounded-lg p-3 text-center border border-indigo-200">
                <p class="text-xs text-indigo-600 mb-0.5">Total deste Log</p>
                <p id="m-total-tokens" class="text-lg font-bold text-indigo-900">—</p>
            </div>
        </div>
        <p id="m-token-method" class="text-xs text-gray-400 text-right -mt-1"></p>
    </div>

    <div id="m-footer" class="hidden border-t border-gray-200">

        {{-- Status: completed — parecer normal da auditoria --}}
        <div id="mf-completed" class="hidden bg-amber-50 px-5 py-4">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-amber-600 font-semibold text-sm">Parecer da Auditoria</span>
                <span id="m-leak" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"></span>
            </div>
            <p id="m-justification" class="text-sm text-gray-700 whitespace-pre-wrap break-words"></p>
        </div>

        {{-- Status: pending / in_analysis — ainda não analisado --}}
        <div id="mf-waiting" class="hidden bg-gray-50 px-5 py-4">
            <p id="m-waiting-msg" class="text-sm text-gray-500 italic"></p>
        </div>

        {{-- Status: failed + admin — motivo técnico (sanitizado no backend) --}}
        <div id="mf-failed-admin" class="hidden bg-red-50 px-5 py-4">
            <p class="text-red-700 font-semibold text-sm mb-2">Falha na Análise</p>
            <p id="m-error-reason" class="text-sm text-red-800 whitespace-pre-wrap break-words font-mono bg-red-100 rounded px-3 py-2 border border-red-200"></p>
        </div>

        {{-- Status: failed + não-admin — mensagem genérica sem detalhes internos --}}
        <div id="mf-failed-generic" class="hidden bg-red-50 px-5 py-4">
            <p class="text-red-700 font-semibold text-sm mb-1">Falha na Análise</p>
            <p class="text-sm text-red-700">A análise deste registro falhou. Entre em contato com um administrador para verificar o motivo.</p>
        </div>

    </div>
</x-modal>

{{-- Form oculto para PDF --}}
<form id="form-pdf" method="POST" action="{{ route('audit.export.pdf', request()->query()) }}" style="display:none">
    @csrf
</form>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const DETAIL_URL = '{{ route("audit.logs.detail", ["log" => "__ID__"]) }}';

    // ── Refresh AJAX ─────────────────────────────────────────────────────────
    const btnRefresh     = document.getElementById('btn-refresh');
    const refreshIcon    = document.getElementById('refresh-icon');
    const tableContainer = document.getElementById('audit-table-container');

    btnRefresh.addEventListener('click', async function () {
        btnRefresh.disabled = true;
        refreshIcon.classList.add('animate-spin');
        try {
            const res = await fetch(window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            tableContainer.style.opacity = '0';
            await new Promise(r => setTimeout(r, 200));
            tableContainer.innerHTML = data.html;
            tableContainer.style.opacity = '1';
        } catch (err) {
            console.error('[GovCert] Erro ao atualizar tabela:', err);
        } finally {
            refreshIcon.classList.remove('animate-spin');
            btnRefresh.disabled = false;
        }
    });

    // ── Modal de chat ────────────────────────────────────────────────────────
    const modal = document.getElementById('chat-modal');

    const leakClasses = {
        'Dados Pessoais': 'bg-orange-100 text-orange-800',
        'Credenciais':    'bg-red-100 text-red-800',
        'Código Fonte':   'bg-purple-100 text-purple-800',
        'Nenhum':         'bg-gray-200 text-gray-700',
    };

    function fmt(n) {
        return n != null ? Number(n).toLocaleString('pt-BR') : 'Não informado';
    }

    /**
     * Renderiza a seção de rodapé do modal de acordo com o status do log.
     * A visibilidade de `error_reason` é controlada pelo backend (null = não-admin).
     */
    function renderFooter(d) {
        const footerSections = ['mf-completed', 'mf-waiting', 'mf-failed-admin', 'mf-failed-generic'];
        footerSections.forEach(id => document.getElementById(id).classList.add('hidden'));
        document.getElementById('m-footer').classList.remove('hidden');

        const status = d.status;

        if (status === 'completed') {
            document.getElementById('mf-completed').classList.remove('hidden');
            document.getElementById('m-justification').textContent =
                d.gemini_justification || 'Análise concluída sem parecer detalhado.';

            const leak  = d.leak_type || '—';
            const badge = document.getElementById('m-leak');
            badge.textContent = leak;
            badge.className   = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium '
                + (leakClasses[leak] || 'bg-gray-200 text-gray-700');

        } else if (status === 'pending') {
            document.getElementById('mf-waiting').classList.remove('hidden');
            document.getElementById('m-waiting-msg').textContent = 'Este registro ainda não foi analisado.';

        } else if (status === 'in_analysis') {
            document.getElementById('mf-waiting').classList.remove('hidden');
            document.getElementById('m-waiting-msg').textContent = 'Este registro está em análise.';

        } else if (status === 'failed') {
            // Backend envia error_reason somente para admin (não-null).
            // Para outros roles envia null → exibe mensagem genérica.
            if (d.error_reason !== null && d.error_reason !== undefined) {
                document.getElementById('mf-failed-admin').classList.remove('hidden');
                document.getElementById('m-error-reason').textContent = d.error_reason;
            } else {
                document.getElementById('mf-failed-generic').classList.remove('hidden');
            }
        } else {
            // Fallback para status desconhecido
            document.getElementById('mf-waiting').classList.remove('hidden');
            document.getElementById('m-waiting-msg').textContent = 'Status desconhecido.';
        }
    }

    async function openModal(logId) {
        document.getElementById('m-loading').classList.remove('hidden');
        document.getElementById('m-content').classList.add('hidden');
        document.getElementById('m-footer').classList.add('hidden');

        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        try {
            const res = await fetch(DETAIL_URL.replace('__ID__', logId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const d = await res.json();

            document.getElementById('m-id').textContent     = '#' + d.id;
            document.getElementById('m-user').textContent   = d.user_identifier || '';
            document.getElementById('m-agent').textContent  = d.ai_agent_name || 'Agente de IA';
            document.getElementById('m-input').textContent  = d.input_masked || '(sem conteúdo)';
            document.getElementById('m-output').textContent = d.output_masked || '(sem resposta)';

            // Tokens apenas deste log
            document.getElementById('m-input-tokens').textContent  = fmt(d.input_tokens);
            document.getElementById('m-output-tokens').textContent = fmt(d.output_tokens);
            document.getElementById('m-total-tokens').textContent  = fmt(d.total_tokens);

            const methodEl = document.getElementById('m-token-method');
            methodEl.textContent = d.token_method ? 'Origem dos tokens: ' + d.token_method : '';

            document.getElementById('m-loading').classList.add('hidden');
            document.getElementById('m-content').classList.remove('hidden');

            renderFooter(d);
        } catch (err) {
            document.getElementById('m-loading').classList.add('hidden');
            console.error('[GovCert] Erro ao carregar detalhe:', err);
        }
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    tableContainer.addEventListener('click', function (e) {
        const row = e.target.closest('.audit-row');
        if (row) openModal(row.dataset.logId);
    });

    document.getElementById('chat-close').addEventListener('click', closeModal);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });
    modal.querySelector('[data-close]')?.addEventListener('click', closeModal);

    // ── Export PDF ───────────────────────────────────────────────────────────
    document.getElementById('btn-export-pdf').addEventListener('click', function () {
        this.disabled = true;
        this.textContent = 'Gerando PDF...';
        document.getElementById('form-pdf').submit();
        setTimeout(() => { this.disabled = false; this.textContent = 'Exportar PDF'; }, 2000);
    });
})();
</script>
@endpush
