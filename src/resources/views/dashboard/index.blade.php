@extends('layouts.app')

@section('title', 'Visão Geral')

@section('content')
<div class="space-y-6">

    {{-- Cabeçalho --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Visão Geral</h1>
            <p class="text-gray-500 text-sm mt-1">Resumo dos dados capturados e análises de governança de IA</p>
        </div>
        <button id="btn-refresh"
                type="button"
                title="Atualizar indicadores"
                class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:border-indigo-400 text-gray-600 hover:text-indigo-600 text-sm px-3 py-2 rounded-lg transition-all duration-300 shadow-sm self-start sm:self-auto">
            <svg id="refresh-icon" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Atualizar
        </button>
    </div>

    {{-- Filtro de período --}}
    <form method="GET" action="{{ route('dashboard') }}" class="bg-white rounded-xl shadow p-4">
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
                <x-button :href="route('dashboard')" variant="secondary">Últimos 30 dias</x-button>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3">
            Período exibido:
            <strong>{{ \Illuminate\Support\Carbon::parse($startDate)->format('d/m/Y') }}</strong>
            a
            <strong>{{ \Illuminate\Support\Carbon::parse($endDate)->format('d/m/Y') }}</strong>
        </p>
    </form>

    {{-- KPI Cards — auditoria --}}
    <div id="kpi-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 transition-all duration-300">
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-indigo-500">
            <p class="text-sm text-gray-500">Total de Logs</p>
            <p class="text-3xl font-bold text-gray-900 mt-1" id="kpi-total">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-yellow-400">
            <p class="text-sm text-gray-500">Aguardando / Em Análise</p>
            <p class="text-3xl font-bold text-gray-900 mt-1" id="kpi-pending">{{ number_format($stats['pending']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-orange-500">
            <p class="text-sm text-gray-500">Dados Sensíveis Detectados</p>
            <p class="text-3xl font-bold text-gray-900 mt-1" id="kpi-sensitive">{{ number_format($stats['sensitive']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-red-600">
            <p class="text-sm text-gray-500">Risco Crítico</p>
            <p class="text-3xl font-bold text-gray-900 mt-1" id="kpi-critical">{{ number_format($stats['critical']) }}</p>
        </div>
    </div>

    {{-- KPI Cards — tokens --}}
    <div id="kpi-tokens-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-indigo-300">
            <p class="text-sm text-gray-500">Total de Tokens</p>
            <p class="text-3xl font-bold text-indigo-700 mt-1" id="kpi-total-tokens">{{ number_format($tokenStats['total']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-indigo-200">
            <p class="text-sm text-gray-500">Média por Log</p>
            <p class="text-3xl font-bold text-indigo-600 mt-1" id="kpi-avg-tokens">{{ number_format($tokenStats['average'], 0) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-purple-400">
            <p class="text-sm text-gray-500">Maior Log</p>
            <p class="text-3xl font-bold text-purple-700 mt-1" id="kpi-max-tokens">{{ number_format($tokenStats['max']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-indigo-400">
            <p class="text-sm text-gray-500">Logs com Tokens</p>
            <p class="text-3xl font-bold text-gray-900 mt-1" id="kpi-token-logs">{{ number_format($tokenStats['log_count']) }}</p>
        </div>
    </div>

    {{-- Gráficos — volume de dados --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl shadow p-6">
            <h2 class="text-base font-semibold text-gray-700 mb-4">
                Volume de Dados Capturados
                ({{ \Illuminate\Support\Carbon::parse($startDate)->format('d/m') }} – {{ \Illuminate\Support\Carbon::parse($endDate)->format('d/m') }})
            </h2>
            <div id="chart-line"></div>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-base font-semibold text-gray-700 mb-4">Distribuição por Nível de Risco</h2>
            <div id="chart-pie"></div>
        </div>
    </div>

    {{-- Gráfico de tokens por dia --}}
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-base font-semibold text-gray-700 mb-4">Consumo de Tokens por Dia</h2>
        <div id="chart-tokens-line"></div>
    </div>

    {{-- Top usuários por risco e por tokens --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-base font-semibold text-gray-700 mb-4">Top Usuários — Dados Sensíveis</h2>
            <div id="chart-bar"></div>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-base font-semibold text-gray-700 mb-4">Top Usuários — Consumo de Tokens</h2>
            <div id="chart-tokens-bar"></div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const lineData       = @json($volumeByDay);
    const riskData       = @json($riskDistribution);
    const userData       = @json($byUser);
    const tokensData     = @json($tokensByDay);
    const topTokenUsers  = @json($topTokenUsers);

    const riskLabels = { low: 'Baixo', medium: 'Médio', high: 'Alto', critical: 'Crítico' };
    const riskColors = { low: '#22c55e', medium: '#eab308', high: '#f97316', critical: '#ef4444' };

    // ── Gráfico de volume por dia ────────────────────────────────────────────
    const chartLine = new ApexCharts(document.getElementById('chart-line'), {
        chart:  { type: 'area', height: 280, toolbar: { show: false } },
        series: [
            { name: 'Total de Logs',       data: lineData.map(d => ({ x: d.log_date, y: d.total })) },
            { name: 'Com Dados Sensíveis', data: lineData.map(d => ({ x: d.log_date, y: d.sensitive_count || 0 })) },
        ],
        colors:     ['#1351B4', '#FFCD07'],
        fill:       { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        xaxis:      { type: 'datetime', labels: { format: 'dd/MM' } },
        yaxis:      { labels: { formatter: v => Math.round(v) } },
        tooltip:    { x: { format: 'dd/MM/yyyy' } },
        stroke:     { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        legend:     { position: 'top' },
    });
    chartLine.render();

    // ── Gráfico de distribuição de risco ─────────────────────────────────────
    const riskKeys = Object.keys(riskData);
    const chartPie = new ApexCharts(document.getElementById('chart-pie'), {
        chart:       { type: 'donut', height: 280 },
        series:      riskKeys.map(k => riskData[k]),
        labels:      riskKeys.map(k => riskLabels[k] || k),
        colors:      riskKeys.map(k => riskColors[k] || '#94a3b8'),
        legend:      { position: 'bottom' },
        dataLabels:  { enabled: true },
        plotOptions: { pie: { donut: { size: '60%' } } },
    });
    chartPie.render();

    // ── Gráfico de tokens por dia ────────────────────────────────────────────
    const chartTokensLine = new ApexCharts(document.getElementById('chart-tokens-line'), {
        chart:  { type: 'area', height: 260, toolbar: { show: false } },
        series: [
            { name: 'Total de Tokens',  data: tokensData.map(d => ({ x: d.log_date, y: d.total_tokens || 0 })) },
            { name: 'Tokens Entrada',   data: tokensData.map(d => ({ x: d.log_date, y: d.input_tokens || 0 })) },
            { name: 'Tokens Saída',     data: tokensData.map(d => ({ x: d.log_date, y: d.output_tokens || 0 })) },
        ],
        colors:     ['#1351B4', '#5992ed', '#FFCD07'],
        fill:       { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.02 } },
        xaxis:      { type: 'datetime', labels: { format: 'dd/MM' } },
        yaxis:      { labels: { formatter: v => Math.round(v).toLocaleString('pt-BR') } },
        tooltip:    { x: { format: 'dd/MM/yyyy' }, y: { formatter: v => v.toLocaleString('pt-BR') + ' tk' } },
        stroke:     { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        legend:     { position: 'top' },
    });
    chartTokensLine.render();

    // ── Top usuários por dados sensíveis ─────────────────────────────────────
    const chartBar = new ApexCharts(document.getElementById('chart-bar'), {
        chart:       { type: 'bar', height: 260, toolbar: { show: false } },
        series:      [{ name: 'Logs com Dados Sensíveis', data: userData.map(u => u.sensitive_count || 0) }],
        xaxis:       { categories: userData.map(u => u.user_identifier) },
        colors:      ['#1351B4'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
        dataLabels:  { enabled: false },
        yaxis:       { labels: { formatter: v => Math.round(v) } },
    });
    chartBar.render();

    // ── Top usuários por tokens ───────────────────────────────────────────────
    const chartTokensBar = new ApexCharts(document.getElementById('chart-tokens-bar'), {
        chart:       { type: 'bar', height: 260, toolbar: { show: false } },
        series:      [{ name: 'Tokens Consumidos', data: topTokenUsers.map(u => u.total_tokens || 0) }],
        xaxis:       { categories: topTokenUsers.map(u => u.user_identifier) },
        colors:      ['#0c326f'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
        dataLabels:  { enabled: false },
        yaxis:       { labels: { formatter: v => v.toLocaleString('pt-BR') } },
        tooltip:     { y: { formatter: v => v.toLocaleString('pt-BR') + ' tk' } },
    });
    chartTokensBar.render();

    // ── Refresh AJAX ──────────────────────────────────────────────────────────
    const btnRefresh   = document.getElementById('btn-refresh');
    const refreshIcon  = document.getElementById('refresh-icon');
    const kpiContainer = document.getElementById('kpi-container');
    const kpiTokens    = document.getElementById('kpi-tokens-container');

    function fmt(n) { return (n ?? 0).toLocaleString('pt-BR'); }

    btnRefresh.addEventListener('click', async function () {
        btnRefresh.disabled = true;
        refreshIcon.classList.add('animate-spin');

        try {
            const res = await fetch(window.location.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            // KPIs auditoria
            kpiContainer.style.opacity = '0';
            kpiTokens.style.opacity    = '0';
            await new Promise(r => setTimeout(r, 200));

            document.getElementById('kpi-total').textContent    = fmt(data.stats.total);
            document.getElementById('kpi-pending').textContent  = fmt(data.stats.pending);
            document.getElementById('kpi-sensitive').textContent= fmt(data.stats.sensitive);
            document.getElementById('kpi-critical').textContent = fmt(data.stats.critical);

            // KPIs tokens
            document.getElementById('kpi-total-tokens').textContent = fmt(data.tokenStats.total);
            document.getElementById('kpi-avg-tokens').textContent   = fmt(Math.round(data.tokenStats.average));
            document.getElementById('kpi-max-tokens').textContent   = fmt(data.tokenStats.max);
            document.getElementById('kpi-token-logs').textContent   = fmt(data.tokenStats.log_count);

            kpiContainer.style.opacity = '1';
            kpiTokens.style.opacity    = '1';

            // Gráficos
            chartLine.updateSeries([
                { name: 'Total de Logs',       data: data.volumeByDay.map(d => ({ x: d.log_date, y: d.total })) },
                { name: 'Com Dados Sensíveis', data: data.volumeByDay.map(d => ({ x: d.log_date, y: d.sensitive_count || 0 })) },
            ]);

            const newRiskKeys = Object.keys(data.riskDistribution);
            chartPie.updateOptions({ labels: newRiskKeys.map(k => riskLabels[k] || k) });
            chartPie.updateSeries(newRiskKeys.map(k => data.riskDistribution[k]));

            chartTokensLine.updateSeries([
                { name: 'Total de Tokens', data: data.tokensByDay.map(d => ({ x: d.log_date, y: d.total_tokens || 0 })) },
                { name: 'Tokens Entrada',  data: data.tokensByDay.map(d => ({ x: d.log_date, y: d.input_tokens || 0 })) },
                { name: 'Tokens Saída',    data: data.tokensByDay.map(d => ({ x: d.log_date, y: d.output_tokens || 0 })) },
            ]);

            chartBar.updateOptions({ xaxis: { categories: data.byUser.map(u => u.user_identifier) } });
            chartBar.updateSeries([{ name: 'Logs com Dados Sensíveis', data: data.byUser.map(u => u.sensitive_count || 0) }]);

            chartTokensBar.updateOptions({ xaxis: { categories: data.topTokenUsers.map(u => u.user_identifier) } });
            chartTokensBar.updateSeries([{ name: 'Tokens Consumidos', data: data.topTokenUsers.map(u => u.total_tokens || 0) }]);

        } catch (err) {
            console.error('[GovCert] Erro ao atualizar dashboard:', err);
        } finally {
            refreshIcon.classList.remove('animate-spin');
            btnRefresh.disabled = false;
        }
    });
})();
</script>
@endpush
