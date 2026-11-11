<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $username = (string) env('PANEL_ADMIN_USERNAME');
        $password = (string) env('PANEL_ADMIN_PASSWORD');

        if ($username === '' || mb_strlen($password) < 12) {
            throw new \RuntimeException('Define PANEL_ADMIN_USERNAME y PANEL_ADMIN_PASSWORD (mínimo 12 caracteres) antes de sembrar.');
        }

        $admin = User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => env('PANEL_ADMIN_NAME', 'Administrador'),
                'password' => Hash::make($password),
            ]
        );
        User::query()->where('id', '!=', $admin->id)->delete();
        \DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', '!=', $admin->id)
            ->delete();

        \DB::table('servers')->updateOrInsert(
            ['is_primary' => true],
            [
                'name' => config('panel.server_name'),
                'hostname' => config('panel.server_hostname'),
                'description' => 'Servidor personal del panel',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        $serverId = (int) \DB::table('servers')->where('is_primary', true)->value('id');

        foreach (config('panel.services') as $unit) {
            \DB::table('services')->updateOrInsert(
                ['unit_name' => $unit],
                ['server_id' => $serverId, 'name' => Str::headline(str_replace(['.service', '.target'], '', $unit)), 'updated_at' => now(), 'created_at' => now()]
            );
        }

        foreach ([
            'cpu_load_1' => ['Carga CPU (1 min)', ''],
            'cpu_load_5' => ['Carga CPU (5 min)', ''],
            'cpu_load_15' => ['Carga CPU (15 min)', ''],
            'ram_used_pct' => ['RAM usada', '%'],
            'swap_used_pct' => ['Swap usada', '%'],
            'disk_used_pct' => ['Disco usado', '%'],
            'temp_cpu_c' => ['Temperatura CPU', '°C'],
            'uptime_s' => ['Tiempo activo', 's'],
        ] as $key => [$label, $unit]) {
            \DB::table('metric_types')->updateOrInsert(['key' => $key], ['label' => $label, 'unit' => $unit]);
        }

        foreach ([
            ['PHP', 'language'], ['Laravel', 'framework'], ['Vue', 'framework'],
            ['MariaDB', 'database'], ['Nginx', 'webserver'], ['Nextcloud', 'other'],
        ] as [$name, $category]) {
            \DB::table('technologies')->updateOrInsert(['name' => $name], ['category' => $category, 'created_at' => now(), 'updated_at' => now()]);
        }

        $nextcloudId = \DB::table('apps')->where('slug', 'nextcloud')->value('id');
        if (! $nextcloudId) {
            $nextcloudId = \DB::table('apps')->insertGetId([
                'server_id' => $serverId,
                'name' => 'Nextcloud',
                'slug' => 'nextcloud',
                'kind' => 'nextcloud',
                'description' => 'Nube personal',
                'local_url' => env('NEXTCLOUD_STATUS_URL', 'http://127.0.0.1/status.php'),
                'exposure' => 'private',
                'environment' => 'personal',
                'lifecycle' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach (['Nextcloud', 'PHP', 'MariaDB', 'Nginx'] as $technologyName) {
            $technologyId = \DB::table('technologies')->where('name', $technologyName)->value('id');
            \DB::table('app_technology')->updateOrInsert(
                ['app_id' => $nextcloudId, 'technology_id' => $technologyId],
                ['version' => null]
            );
        }

        $checkId = \DB::table('checks')->where('app_id', $nextcloudId)->where('type', 'http')->value('id');
        if (! $checkId) {
            $checkId = \DB::table('checks')->insertGetId([
                'app_id' => $nextcloudId,
                'type' => 'http',
                'url' => env('NEXTCLOUD_STATUS_URL', 'http://127.0.0.1/status.php'),
                'expected_status' => 200,
                'timeout_ms' => 3000,
                'interval_seconds' => 60,
                'failure_threshold' => 3,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Valida los umbrales de temperatura con la ficha técnica del procesador antes de activarlos.
        $rules = [
            ['Disco ≥ 85 %', 'disk_used_pct', 'gte', 85, 0, 0, 'warning'],
            ['Disco ≥ 92 %', 'disk_used_pct', 'gte', 92, 0, 0, 'critical'],
            ['RAM ≥ 90 %', 'ram_used_pct', 'gte', 90, 5, 0, 'warning'],
            ['Swap ≥ 60 %', 'swap_used_pct', 'gte', 60, 5, 0, 'warning'],
            ['Carga a 5 min ≥ 2', 'cpu_load_5', 'gte', 2, 10, 0, 'warning'],
            ['Temperatura CPU ≥ 70 °C', 'temp_cpu_c', 'gte', 70, 3, 0, 'warning'],
            ['Temperatura CPU ≥ 80 °C', 'temp_cpu_c', 'gte', 80, 3, 0, 'critical'],
        ];
        foreach ($rules as [$name, $metric, $comparison, $threshold, $sustain, $hysteresis, $severity]) {
            \DB::table('alert_rules')->updateOrInsert(
                ['name' => $name],
                [
                    'metric_type_id' => \DB::table('metric_types')->where('key', $metric)->value('id'),
                    'check_id' => null,
                    'comparison' => $comparison,
                    'threshold' => $threshold,
                    'sustain_minutes' => $sustain,
                    'hysteresis' => $hysteresis,
                    'severity' => $severity,
                    'renotify_minutes' => 60,
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        foreach (\DB::table('services')->get() as $service) {
            \DB::table('checks')->updateOrInsert(
                ['service_id' => $service->id, 'type' => 'systemd'],
                ['app_id' => null, 'url' => null, 'failure_threshold' => 2, 'interval_seconds' => 60, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]
            );
            $serviceCheck = \DB::table('checks')->where('service_id', $service->id)->where('type', 'systemd')->value('id');
            \DB::table('alert_rules')->updateOrInsert(
                ['name' => "Servicio {$service->unit_name} inactivo"],
                ['metric_type_id' => null, 'check_id' => $serviceCheck, 'comparison' => 'status_down', 'threshold' => null, 'sustain_minutes' => 0, 'hysteresis' => 0, 'severity' => 'critical', 'renotify_minutes' => 60, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        \DB::table('alert_rules')->updateOrInsert(
            ['name' => 'Nextcloud sin respuesta'],
            ['metric_type_id' => null, 'check_id' => $checkId, 'comparison' => 'status_down', 'threshold' => null, 'sustain_minutes' => 0, 'hysteresis' => 0, 'severity' => 'critical', 'renotify_minutes' => 60, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
