<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\SaveAppRequest;
use App\Http\Requests\UpdateAlertRuleRequest;
use App\Http\Resources\AppResource;
use App\Http\Resources\ServerStatusResource;
use App\Models\User;
use App\Notifications\PushNotifier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PanelController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Usuario o contraseña incorrectos.'], 422);
        }

        $token = $user->createToken($data['device_name'], ['*'], now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => now()->addDays(30)->toISOString(),
            'user' => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['id' => $user->id, 'name' => $user->name, 'username' => $user->username]);
    }

    public function status(): JsonResponse
    {
        $server = DB::table('servers')->where('is_primary', true)->first();
        if (! $server) {
            return response()->json(['message' => 'No hay un servidor configurado.'], 503);
        }

        $metrics = DB::table('server_metrics')
            ->join('metric_types', 'metric_types.id', '=', 'server_metrics.metric_type_id')
            ->where('server_id', $server->id)
            ->get(['metric_types.key', 'metric_types.unit', 'server_metrics.dimension', 'server_metrics.value', 'server_metrics.collected_at']);
        $latest = $metrics->max('collected_at');
        $services = DB::table('services')
            ->leftJoin('checks', fn ($join) => $join->on('checks.service_id', '=', 'services.id')->where('checks.type', '=', 'systemd'))
            ->leftJoin('check_states', 'check_states.check_id', '=', 'checks.id')
            ->where('services.server_id', $server->id)
            ->get(['services.name', 'services.unit_name', 'check_states.status', 'check_states.last_checked_at', 'check_states.message']);
        $activeAlertCount = DB::table('alert_events')->where('state', 'firing')->count();
        $hasCritical = DB::table('alert_events')->where('state', 'firing')->where('severity', 'critical')->exists();
        $serviceDown = $services->contains(fn ($service) => $service->status === 'down');
        $serviceDegraded = $services->contains(fn ($service) => $service->status === 'degraded');
        $health = ($hasCritical || $serviceDown) ? 'critical'
            : ($activeAlertCount > 0 || $serviceDegraded ? 'warning' : ($latest ? 'ok' : 'unknown'));
        $latestDate = $latest ? now()->parse($latest) : null;

        return ServerStatusResource::make([
            'server' => ['id' => $server->id, 'name' => $server->name, 'hostname' => $server->hostname],
            'metrics' => $metrics->groupBy('key')->map(fn ($rows) => $rows->map(fn ($row) => [
                'dimension' => $row->dimension,
                'value' => (float) $row->value,
                'unit' => $row->unit,
                'collected_at' => $row->collected_at,
            ])->values()),
            'services' => $services,
            'overall_health' => $health,
            'collected_at' => $latestDate?->toISOString(),
            'is_stale' => ! $latestDate || $latestDate->diffInSeconds(now()) > config('panel.stale_after_seconds'),
            'lock_timeout_seconds' => config('panel.lock_timeout_seconds'),
        ])->response();
    }

    public function apps(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['up', 'degraded', 'down', 'unknown', 'disabled'])],
            'q' => ['sometimes', 'string', 'max:100'],
        ]);
        $query = DB::table('apps')->whereNull('deleted_at')->orderBy('name');
        if (! empty($filters['q'])) {
            $query->where('name', 'like', '%'.addcslashes($filters['q'], '%_\\').'%');
        }
        $apps = $query->get();
        $result = $apps->map(fn ($app) => $this->appPayload($app, false))
            ->filter(fn ($app) => empty($filters['status']) || $app['status'] === $filters['status'])
            ->values();

        return AppResource::collection($result)->response();
    }

    public function showApp(string $app): JsonResponse
    {
        return AppResource::make($this->appPayload($this->findApp($app), true))->response();
    }

    public function storeApp(SaveAppRequest $request): JsonResponse
    {
        $data = $request->validated();
        $serverId = DB::table('servers')->where('is_primary', true)->value('id');
        abort_if(! $serverId, 503, 'No hay un servidor configurado.');
        $id = DB::transaction(function () use ($data, $serverId) {
            $id = DB::table('apps')->insertGetId($this->appValues($data, (int) $serverId));
            $this->syncTechnologies($id, $data['technologies'] ?? []);
            if (! empty($data['local_url'])) {
                $checkId = DB::table('checks')->insertGetId([
                    'app_id' => $id,
                    'type' => 'http',
                    'url' => $data['local_url'],
                    'expected_status' => 200,
                    'timeout_ms' => 3000,
                    'interval_seconds' => 60,
                    'failure_threshold' => 3,
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->ensureAppAlertRule((int) $checkId, (int) $id, $data['name'], $data['kind'] === 'nextcloud' ? 'critical' : 'warning');
            }

            return $id;
        });

        return AppResource::make($this->appPayload($this->findApp((string) $id), true))->response()->setStatusCode(201);
    }

    public function updateApp(SaveAppRequest $request, string $app): JsonResponse
    {
        $current = $this->findApp($app);
        $data = $request->validated();
        DB::transaction(function () use ($current, $data) {
            DB::table('apps')->where('id', $current->id)->update($this->appValues($data, (int) $current->server_id, true));
            if (array_key_exists('technologies', $data)) {
                $this->syncTechnologies((int) $current->id, $data['technologies']);
            }
            if (array_key_exists('local_url', $data)) {
                $check = DB::table('checks')->where('app_id', $current->id)->where('type', 'http')->first();
                if ($data['local_url'] === null) {
                    if ($check) {
                        DB::table('checks')->where('id', $check->id)->update(['is_enabled' => false, 'updated_at' => now()]);
                    }
                } elseif ($check) {
                    DB::table('checks')->where('id', $check->id)->update(['url' => $data['local_url'], 'is_enabled' => true, 'updated_at' => now()]);
                } else {
                    $checkId = DB::table('checks')->insertGetId([
                        'app_id' => $current->id, 'type' => 'http', 'url' => $data['local_url'],
                        'expected_status' => 200, 'timeout_ms' => 3000, 'interval_seconds' => 60,
                        'failure_threshold' => 3, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $this->ensureAppAlertRule((int) $checkId, (int) $current->id, $current->name, $current->kind === 'nextcloud' ? 'critical' : 'warning');
                }
            }
        });

        return AppResource::make($this->appPayload($this->findApp($app), true))->response();
    }

    public function archiveApp(string $app): JsonResponse
    {
        $current = $this->findApp($app);
        DB::table('apps')->where('id', $current->id)->update(['lifecycle' => 'archived', 'updated_at' => now()]);

        return response()->json(['message' => 'La aplicación quedó archivada; no se ejecutó ninguna acción en el servidor.']);
    }

    public function technologies(): JsonResponse
    {
        return response()->json(['data' => DB::table('technologies')->orderBy('name')->get(['id', 'name', 'category'])]);
    }

    public function storeTechnology(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:technologies,name'],
            'category' => ['required', Rule::in(['language', 'framework', 'database', 'webserver', 'runtime', 'other'])],
        ]);
        $id = DB::table('technologies')->insertGetId($data);

        return response()->json(['data' => DB::table('technologies')->find($id)], 201);
    }

    public function alerts(Request $request): JsonResponse
    {
        $filters = $request->validate(['state' => ['sometimes', Rule::in(['firing', 'resolved'])]]);
        $query = DB::table('alert_events')->orderByDesc('started_at');
        if (! empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }
        $active = DB::table('alert_events')->where('state', 'firing')->orderByDesc('started_at')->get();
        $recent = $query->limit(50)->get();

        return response()->json(['active' => $active, 'events' => $recent]);
    }

    public function alertRules(): JsonResponse
    {
        return response()->json(['data' => DB::table('alert_rules')->orderBy('id')->get()]);
    }

    public function updateAlertRule(UpdateAlertRuleRequest $request, int $id): JsonResponse
    {
        abort_unless(DB::table('alert_rules')->where('id', $id)->exists(), 404);
        $data = $request->validated();
        DB::table('alert_rules')->where('id', $id)->update([...$data, 'updated_at' => now()]);

        return response()->json(['data' => DB::table('alert_rules')->find($id)]);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'platform' => ['required', Rule::in(['android'])],
            'fcm_token' => ['required', 'string', 'max:512'],
        ]);
        $userId = (int) $request->user()->id;
        $existing = DB::table('devices')->where('fcm_token', $data['fcm_token'])->first();
        if ($existing && $existing->user_id !== $userId) {
            abort(409, 'El token ya está registrado en otro dispositivo.');
        }
        DB::table('devices')->updateOrInsert(
            ['fcm_token' => $data['fcm_token']],
            [...$data, 'user_id' => $userId, 'last_seen_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );
        $device = DB::table('devices')->where('fcm_token', $data['fcm_token'])->first();

        return response()->json(['data' => $device], 201);
    }

    public function deleteDevice(Request $request, int $id): JsonResponse
    {
        DB::table('devices')->where('id', $id)->where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Dispositivo eliminado.']);
    }

    public function testDevice(Request $request, int $id): JsonResponse
    {
        $device = DB::table('devices')->where('id', $id)->where('user_id', $request->user()->id)->first();
        abort_unless($device, 404);
        app(PushNotifier::class)->send($device->fcm_token, [
            'title' => 'Notificación de prueba',
            'body' => 'El canal de alertas está configurado.',
            'severity' => 'info',
            'data' => ['type' => 'test', 'route' => '/tabs/alerts'],
        ]);

        return response()->json(['message' => 'Notificación enviada.']);
    }

    private function findApp(string $id): object
    {
        return DB::table('apps')->whereNull('deleted_at')
            ->where(fn ($query) => ctype_digit($id) ? $query->where('id', (int) $id) : $query->where('slug', $id))
            ->first() ?? throw (new ModelNotFoundException)->setModel('App\\Models\\App');
    }

    private function appPayload(object $app, bool $detail): array
    {
        $checks = DB::table('checks')->leftJoin('check_states', 'check_states.check_id', '=', 'checks.id')
            ->where('checks.app_id', $app->id)->get([
                'checks.id', 'checks.type', 'checks.url', 'check_states.status', 'check_states.response_ms',
                'check_states.last_checked_at', 'check_states.message',
            ]);
        $state = $checks->firstWhere('type', 'http');
        $status = $app->lifecycle !== 'active' ? 'disabled' : ($state->status ?? 'unknown');
        $payload = [
            'id' => $app->id,
            'name' => $app->name,
            'slug' => $app->slug,
            'kind' => $app->kind,
            'status' => $status,
            'response_ms' => $state->response_ms ?? null,
            'description' => $app->description,
            'environment' => $app->environment,
            'lifecycle' => $app->lifecycle,
        ];
        if (! $detail) {
            return $payload;
        }
        $payload += [
            'repository_url' => $app->repository_url,
            'local_url' => $app->local_url,
            'public_url' => $app->public_url,
            'exposure' => $app->exposure,
            'port' => $app->port,
            'notes' => $app->notes,
            'checks' => $checks,
            'technologies' => DB::table('app_technology')
                ->join('technologies', 'technologies.id', '=', 'app_technology.technology_id')
                ->where('app_id', $app->id)->get(['technologies.id', 'technologies.name', 'technologies.category', 'app_technology.version']),
        ];

        return $payload;
    }

    private function appValues(array $data, int $serverId, bool $partial = false): array
    {
        $keys = ['name', 'slug', 'kind', 'description', 'repository_url', 'local_url', 'public_url', 'exposure', 'environment', 'lifecycle', 'port', 'notes'];
        $values = array_intersect_key($data, array_flip($keys));
        if (! $partial) {
            $values['server_id'] = $serverId;
            $values['created_at'] = now();
        }
        $values['updated_at'] = now();

        return $values;
    }

    private function syncTechnologies(int $appId, array $technologies): void
    {
        DB::table('app_technology')->where('app_id', $appId)->delete();
        foreach ($technologies as $technology) {
            DB::table('app_technology')->insert([
                'app_id' => $appId,
                'technology_id' => $technology['id'],
                'version' => $technology['version'] ?? null,
            ]);
        }
    }

    private function ensureAppAlertRule(int $checkId, int $appId, string $name, string $severity): void
    {
        DB::table('alert_rules')->updateOrInsert(
            ['check_id' => $checkId],
            [
                'name' => "{$name} sin respuesta",
                'metric_type_id' => null,
                'comparison' => 'status_down',
                'threshold' => null,
                'sustain_minutes' => 0,
                'hysteresis' => 0,
                'severity' => $severity,
                'renotify_minutes' => 60,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
