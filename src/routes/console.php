<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recupera logs presos em in_analysis há mais de 15 minutos.
// Cenário: worker foi encerrado abruptamente durante o processamento.
// --inline: processa diretamente no processo do scheduler, sem enfileirar,
// para não depender do worker estar ativo nesse momento de recuperação.
Schedule::command('audit:process-pending --status=in_analysis --older-than=15 --limit=50 --inline')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Reenfileira logs pending que ficaram órfãos (Redis perdeu os jobs ou
// o worker estava fora quando o log foi criado).
// older-than=10 dá margem para o worker processar normalmente antes de intervir.
Schedule::command('audit:process-pending --status=pending --older-than=10 --limit=100')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
