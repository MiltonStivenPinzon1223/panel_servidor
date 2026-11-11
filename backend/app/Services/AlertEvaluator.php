<?php

namespace App\Services;

use App\Notifications\PushNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AlertEvaluator
{
    public function evaluate(int $serverId, ?int $deadline = null): void
    {
        $rules = DB::table('alert_rules')->where('is_enabled', true)->get();
        foreach ($rules as $rule) {
            if ($deadline !== null && hrtime(true) >= $deadline) {
                break;
            }
            if ($rule->comparison === 'status_down') {
                $check = DB::table('checks')->join('check_states', 'check_states.check_id', '=', 'checks.id')
                    ->where('checks.id', $rule->check_id)->first(['checks.id', 'checks.app_id', 'check_states.status', 'check_states.consecutive_failures']);
                $this->evaluateValue(
                    $rule,
                    '',
                    $check && $check->status === 'down',
                    $check ? (float) $check->consecutive_failures : null,
                    $check?->app_id,
                    $deadline
                );

                continue;
            }

            $values = DB::table('server_metrics')->where('server_id', $serverId)
                ->where('metric_type_id', $rule->metric_type_id)->get(['dimension', 'value']);
            foreach ($values as $metric) {
                if ($deadline !== null && hrtime(true) >= $deadline) {
                    break 2;
                }
                $value = (float) $metric->value;
                $breached = match ($rule->comparison) {
                    'gt' => $value > (float) $rule->threshold,
                    'gte' => $value >= (float) $rule->threshold,
                    'lt' => $value < (float) $rule->threshold,
                    'lte' => $value <= (float) $rule->threshold,
                    default => false,
                };
                $active = DB::table('alert_events')->where('alert_rule_id', $rule->id)
                    ->where('dimension', $metric->dimension)->where('state', 'firing')->exists();
                if ($active) {
                    $releaseThreshold = match ($rule->comparison) {
                        'gt', 'gte' => (float) $rule->threshold - (float) $rule->hysteresis,
                        'lt', 'lte' => (float) $rule->threshold + (float) $rule->hysteresis,
                        default => (float) $rule->threshold,
                    };
                    $breached = match ($rule->comparison) {
                        'gt', 'gte' => $value >= $releaseThreshold,
                        'lt', 'lte' => $value <= $releaseThreshold,
                        default => $breached,
                    };
                }
                $this->evaluateValue($rule, (string) $metric->dimension, $breached, $value, null, $deadline);
            }
        }
    }

    private function evaluateValue(
        object $rule,
        string $dimension,
        bool $breached,
        ?float $value,
        ?int $appId,
        ?int $deadline
    ): void {
        $now = now();
        $state = DB::table('alert_rule_states')->where('alert_rule_id', $rule->id)->where('dimension', $dimension)->first();
        $active = DB::table('alert_events')->where('alert_rule_id', $rule->id)->where('dimension', $dimension)->where('state', 'firing')->first();

        if (! $breached) {
            DB::table('alert_rule_states')->where('alert_rule_id', $rule->id)->where('dimension', $dimension)->delete();
            if ($active) {
                DB::table('alert_events')->where('id', $active->id)->update([
                    'state' => 'resolved',
                    'resolved_at' => $now,
                    'last_notified_at' => null,
                ]);
                $this->notify($rule, (int) $active->id, $appId, 'Alerta resuelta', "Se resolvió: {$rule->name}", 'info', $deadline);
            }

            return;
        }

        if (! $state || ! $state->breach_since) {
            DB::table('alert_rule_states')->updateOrInsert(
                ['alert_rule_id' => $rule->id, 'dimension' => $dimension],
                ['breach_since' => $now]
            );
            $state = (object) ['breach_since' => $now];
        }
        if ($active || $now->diffInMinutes($state->breach_since) < (int) $rule->sustain_minutes) {
            return;
        }

        $eventId = DB::table('alert_events')->insertGetId([
            'alert_rule_id' => $rule->id,
            'dimension' => $dimension,
            'state' => 'firing',
            'severity' => $rule->severity,
            'value' => $value,
            'message' => mb_substr($rule->name, 0, 255),
            'started_at' => $now,
            'last_notified_at' => null,
        ]);
        $this->notify($rule, $eventId, $appId, 'Alerta '.$rule->severity, $rule->name, $rule->severity, $deadline);
    }

    public function renotify(?int $deadline = null): void
    {
        $events = DB::table('alert_events')->join('alert_rules', 'alert_rules.id', '=', 'alert_events.alert_rule_id')
            ->where(fn ($query) => $query
                ->where('alert_events.state', 'firing')
                ->orWhere(fn ($resolved) => $resolved->where('alert_events.state', 'resolved')->whereNull('alert_events.last_notified_at')))
            ->get([
                'alert_events.id', 'alert_events.alert_rule_id', 'alert_events.dimension', 'alert_events.last_notified_at',
                'alert_events.state',
                'alert_rules.name', 'alert_rules.severity', 'alert_rules.check_id', 'alert_rules.renotify_minutes',
            ])->filter(fn ($event) => ! $event->last_notified_at
                || ($event->state === 'firing'
                    && now()->diffInMinutes(Carbon::parse($event->last_notified_at)) >= (int) $event->renotify_minutes));

        foreach ($events as $event) {
            if ($deadline !== null && $deadline - hrtime(true) < 5_000_000_000) {
                break;
            }
            $appId = DB::table('checks')->where('id', $event->check_id)->value('app_id');
            $resolved = $event->state === 'resolved';
            $this->notify(
                $event,
                (int) $event->id,
                $appId,
                $resolved ? 'Alerta resuelta' : 'Alerta activa',
                $resolved ? "Se resolvió: {$event->name}" : $event->name,
                $resolved ? 'info' : $event->severity,
                $deadline
            );
        }
    }

    private function notify(
        object $rule,
        int $eventId,
        ?int $appId,
        string $title,
        string $body,
        string $severity,
        ?int $deadline
    ): void {
        $devices = DB::table('devices')->whereNotNull('fcm_token')->get(['fcm_token']);
        $sent = false;
        foreach ($devices as $device) {
            if ($deadline !== null && $deadline - hrtime(true) < 5_000_000_000) {
                break;
            }
            try {
                $delivered = app(PushNotifier::class)->send($device->fcm_token, [
                    'title' => $title,
                    'body' => $body,
                    'severity' => $severity,
                    'data' => [
                        'type' => 'alert',
                        'alert_event_id' => (string) $eventId,
                        'route' => $appId ? '/tabs/apps/'.$appId : '/tabs/alerts',
                    ],
                ]);
                $sent = $sent || $delivered;
            } catch (RuntimeException $exception) {
                Log::warning('No se pudo enviar una notificación FCM.', ['event_id' => $eventId, 'error' => $exception->getMessage()]);
            }
        }
        if ($sent) {
            DB::table('alert_events')->where('id', $eventId)->update(['last_notified_at' => now()]);
        }
    }
}
