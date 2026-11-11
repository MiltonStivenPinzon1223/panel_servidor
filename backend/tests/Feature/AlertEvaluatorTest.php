<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PushNotifier;
use App\Services\AlertEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AlertEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('La extensión pdo_sqlite es necesaria para las pruebas de integración locales.');
        }

        parent::setUp();
        Carbon::setTestNow('2026-01-01 00:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_alert_fires_after_sustain_without_duplicate_incidents_and_resolves(): void
    {
        $user = User::factory()->create();
        \DB::table('devices')->insert([
            'user_id' => $user->id, 'name' => 'Test', 'platform' => 'android', 'fcm_token' => 'test-fcm-token',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $notifier = $this->mock(PushNotifier::class);
        $notifier->shouldReceive('send')->times(3)->andReturn(true);

        $serverId = \DB::table('servers')->insertGetId([
            'name' => 'Test', 'hostname' => 'test', 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $metricId = \DB::table('metric_types')->insertGetId(['key' => 'ram_used_pct', 'label' => 'RAM', 'unit' => '%']);
        $ruleId = \DB::table('alert_rules')->insertGetId([
            'name' => 'RAM alta', 'metric_type_id' => $metricId, 'comparison' => 'gte',
            'threshold' => 90, 'sustain_minutes' => 5, 'hysteresis' => 2,
            'severity' => 'warning', 'renotify_minutes' => 1, 'is_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('server_metrics')->insert([
            'server_id' => $serverId, 'metric_type_id' => $metricId,
            'dimension' => '', 'value' => 95, 'collected_at' => now(),
        ]);
        $evaluator = app(AlertEvaluator::class);
        $evaluator->evaluate($serverId);
        $this->assertDatabaseCount('alert_events', 0);

        Carbon::setTestNow('2026-01-01 00:05:00');
        $evaluator->evaluate($serverId);
        $this->assertDatabaseCount('alert_events', 1);
        $this->assertDatabaseHas('alert_events', ['alert_rule_id' => $ruleId, 'state' => 'firing']);

        Carbon::setTestNow('2026-01-01 00:06:00');
        $evaluator->evaluate($serverId);
        $this->assertDatabaseCount('alert_events', 1);
        $evaluator->renotify();
        $this->assertDatabaseHas('alert_events', ['id' => 1, 'last_notified_at' => '2026-01-01 00:06:00']);

        \DB::table('server_metrics')->where('server_id', $serverId)->update(['value' => 80]);
        $evaluator->evaluate($serverId);
        $this->assertDatabaseHas('alert_events', ['alert_rule_id' => $ruleId, 'state' => 'resolved']);
    }
}
