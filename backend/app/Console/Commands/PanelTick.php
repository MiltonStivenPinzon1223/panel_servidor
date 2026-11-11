<?php

namespace App\Console\Commands;

use App\Services\AlertEvaluator;
use App\Services\HostCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PanelTick extends Command
{
    protected $signature = 'panel:tick';

    protected $description = 'Recopila métricas actuales y evalúa salud y alertas.';

    public function handle(HostCollector $collector, AlertEvaluator $alerts): int
    {
        $started = hrtime(true);
        $deadline = $started + 24_000_000_000;
        try {
            $collector->collect($deadline);
            $alerts->renotify($deadline);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Falló el ciclo del colector.', ['error' => $exception->getMessage()]);
            $this->error('Falló el ciclo del colector; revisa el log de Laravel.');

            return self::FAILURE;
        } finally {
            Log::info('Duración total de panel:tick.', ['duration_ms' => round((hrtime(true) - $started) / 1_000_000, 2)]);
        }
    }
}
