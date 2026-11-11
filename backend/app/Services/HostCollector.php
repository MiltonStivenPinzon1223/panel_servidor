<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class HostCollector
{
    public function collect(?int $deadline = null): void
    {
        $started = hrtime(true);
        $deadline ??= $started + 24_000_000_000;
        $serverId = DB::table('servers')->where('is_primary', true)->value('id');
        if (! $serverId) {
            Log::warning('El colector no encontró un servidor primario sembrado.');

            return;
        }

        $observed = $this->readHostMetrics();
        $this->storeMetrics((int) $serverId, $observed);
        $this->checkServices($deadline);
        $this->checkHttp($deadline);
        app(AlertEvaluator::class)->evaluate((int) $serverId, $deadline);

        Log::info('Ciclo panel:tick completado.', ['duration_ms' => round((hrtime(true) - $started) / 1_000_000, 2)]);
    }

    /** @return array<string, array<string, float>> */
    public function readHostMetrics(): array
    {
        $proc = rtrim(config('panel.proc_path'), '/');
        $metrics = [];
        $load = @file_get_contents($proc.'/loadavg');
        if ($load !== false && preg_match('/^([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)/', $load, $matches)) {
            $metrics['cpu_load_1'][''] = (float) $matches[1];
            $metrics['cpu_load_5'][''] = (float) $matches[2];
            $metrics['cpu_load_15'][''] = (float) $matches[3];
        }

        $memInfo = @file_get_contents($proc.'/meminfo');
        if ($memInfo !== false) {
            $values = [];
            foreach (['MemTotal', 'MemAvailable', 'SwapTotal', 'SwapFree'] as $key) {
                if (preg_match('/^'.$key.':\s+(\d+)\s+kB$/m', $memInfo, $matches)) {
                    $values[$key] = (float) $matches[1];
                }
            }
            if (($values['MemTotal'] ?? 0) > 0 && isset($values['MemAvailable'])) {
                $metrics['ram_used_pct'][''] = max(0, ($values['MemTotal'] - $values['MemAvailable']) / $values['MemTotal'] * 100);
            }
            if (($values['SwapTotal'] ?? 0) > 0 && isset($values['SwapFree'])) {
                $metrics['swap_used_pct'][''] = max(0, ($values['SwapTotal'] - $values['SwapFree']) / $values['SwapTotal'] * 100);
            }
        }

        $uptime = @file_get_contents($proc.'/uptime');
        if ($uptime !== false && preg_match('/^([0-9.]+)/', $uptime, $matches)) {
            $metrics['uptime_s'][''] = (float) $matches[1];
        }

        foreach (config('panel.mounts') as $mount) {
            $total = @disk_total_space($mount);
            $free = @disk_free_space($mount);
            if ($total !== false && $total > 0 && $free !== false) {
                $metrics['disk_used_pct'][$mount] = max(0, ($total - $free) / $total * 100);
            }
        }

        $temperature = $this->readTemperature();
        if ($temperature !== null) {
            $metrics['temp_cpu_c'][''] = $temperature;
        }

        return $metrics;
    }

    private function readTemperature(): ?float
    {
        $root = rtrim(config('panel.sys_path'), '/');
        $paths = [];
        foreach (glob($root.'/class/thermal/thermal_zone*/temp') ?: [] as $path) {
            $type = @file_get_contents(dirname($path).'/type');
            if ($type !== false && preg_match('/cpu|x86_pkg_temp|k10temp|coretemp|zenpower/i', $type)) {
                $paths[] = $path;
            }
        }
        foreach (glob($root.'/class/hwmon/hwmon*/temp*_input') ?: [] as $path) {
            $name = @file_get_contents(dirname($path).'/name');
            $label = @file_get_contents(str_replace('_input', '_label', $path));
            if (($name !== false && preg_match('/cpu|k10temp|coretemp|zenpower/i', $name))
                || ($label !== false && preg_match('/cpu|tctl|tdie|core/i', $label))) {
                $paths[] = $path;
            }
        }
        foreach ($paths as $path) {
            $raw = @file_get_contents($path);
            if ($raw !== false && is_numeric(trim($raw))) {
                $value = (float) trim($raw);

                return $value > 1000 ? $value / 1000 : $value;
            }
        }

        return null;
    }

    private function storeMetrics(int $serverId, array $metrics): void
    {
        $types = DB::table('metric_types')->pluck('id', 'key');
        if (! array_key_exists('temp_cpu_c', $metrics) && isset($types['temp_cpu_c'])) {
            DB::table('server_metrics')->where('server_id', $serverId)->where('metric_type_id', $types['temp_cpu_c'])->delete();
        }
        $rows = [];
        foreach ($metrics as $key => $dimensions) {
            if (! isset($types[$key])) {
                continue;
            }
            foreach ($dimensions as $dimension => $value) {
                $rows[] = [
                    'server_id' => $serverId,
                    'metric_type_id' => $types[$key],
                    'dimension' => (string) $dimension,
                    'value' => $value,
                    'collected_at' => now(),
                ];
            }
        }
        if ($rows !== []) {
            DB::table('server_metrics')->upsert(
                $rows,
                ['server_id', 'metric_type_id', 'dimension'],
                ['value', 'collected_at']
            );
        }
    }

    private function checkServices(int $deadline): void
    {
        if (! config('panel.systemctl_enabled')) {
            return;
        }
        $allowed = config('panel.services');
        $services = DB::table('services')->whereIn('unit_name', $allowed)
            ->join('checks', fn ($join) => $join->on('checks.service_id', '=', 'services.id')->where('checks.type', 'systemd'))
            ->get(['services.id', 'services.unit_name', 'checks.id as check_id', 'checks.failure_threshold']);
        foreach ($services as $service) {
            if (hrtime(true) >= $deadline) {
                break;
            }
            $started = hrtime(true);
            try {
                $process = new Process([config('panel.systemctl_binary'), 'is-active', $service->unit_name]);
                $process->setTimeout(1.5);
                $process->run();
                $active = trim($process->getOutput()) === 'active' && $process->isSuccessful();
                $message = $active ? 'Activo' : 'Inactivo';
            } catch (ProcessTimedOutException) {
                $active = false;
                $message = 'La consulta de estado excedió el tiempo límite.';
            }
            $this->updateCheckState(
                (int) $service->check_id,
                $active,
                (int) round((hrtime(true) - $started) / 1_000_000),
                $message,
                (int) $service->failure_threshold
            );
        }
    }

    private function checkHttp(int $deadline): void
    {
        $due = DB::table('checks')->leftJoin('check_states', 'check_states.check_id', '=', 'checks.id')
            ->where('checks.type', 'http')->where('checks.is_enabled', true)
            ->select('checks.*', 'check_states.last_checked_at')->limit(20)->get()
            ->filter(fn ($check) => ! $check->last_checked_at
                || Carbon::parse($check->last_checked_at)->addSeconds((int) $check->interval_seconds)->lessThanOrEqualTo(now()));

        foreach ($due as $check) {
            if (hrtime(true) >= $deadline) {
                break;
            }
            $host = strtolower((string) parse_url((string) $check->url, PHP_URL_HOST));
            if (! in_array($host, config('panel.allowed_check_hosts'), true)) {
                $this->storeHttpCheck($check, false, null, 'Host fuera de la lista permitida.');

                continue;
            }
            $remaining = max(0.1, ($deadline - hrtime(true)) / 1_000_000_000);
            $timeout = min(max(0.1, (int) $check->timeout_ms / 1000), $remaining);
            $started = hrtime(true);
            try {
                $response = Http::withoutRedirecting()->timeout($timeout)->get($check->url);
                $elapsed = (int) round((hrtime(true) - $started) / 1_000_000);
                $success = $response->status() === (int) $check->expected_status;
                $this->storeHttpCheck($check, $success, $elapsed, $success ? 'Respuesta HTTP correcta.' : 'Código HTTP inesperado: '.$response->status());
            } catch (ConnectionException) {
                $this->storeHttpCheck($check, false, null, 'No fue posible conectar con la aplicación.');
            }
        }
    }

    private function storeHttpCheck(object $check, bool $success, ?int $elapsed, string $message): void
    {
        $this->updateCheckState((int) $check->id, $success, $elapsed, $message, (int) $check->failure_threshold, true);
    }

    private function updateCheckState(
        int $checkId,
        bool $success,
        ?int $elapsed,
        string $message,
        int $threshold = 1,
        bool $httpCheck = false
    ): void {
        $old = DB::table('check_states')->where('check_id', $checkId)->first();
        $failures = $success ? 0 : (int) ($old->consecutive_failures ?? 0) + 1;
        $status = $success
            ? ($httpCheck && ($elapsed ?? 0) > config('panel.http_degraded_ms') ? 'degraded' : 'up')
            : ($failures >= $threshold ? 'down' : 'degraded');
        $now = now();
        DB::table('check_states')->updateOrInsert(
            ['check_id' => $checkId],
            [
                'status' => $status,
                'response_ms' => $elapsed,
                'consecutive_failures' => $failures,
                'last_checked_at' => $now,
                'last_changed_at' => ! $old || $old->status !== $status ? $now : $old->last_changed_at,
                'message' => mb_substr($message, 0, 255),
            ]
        );
    }
}
