<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('platform', 24)->default('android');
            $table->string('fcm_token', 512)->nullable()->unique();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hostname')->index();
            $table->string('description', 500)->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit_name')->unique();
            $table->timestamps();
        });

        Schema::create('technologies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category', 24)->index();
            $table->timestamps();
        });

        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kind', 24)->index();
            $table->text('description')->nullable();
            $table->string('repository_url', 2048)->nullable();
            $table->string('local_url', 2048)->nullable();
            $table->string('public_url', 2048)->nullable();
            $table->string('exposure', 24)->default('private')->index();
            $table->string('environment', 24)->default('personal')->index();
            $table->string('lifecycle', 24)->default('active')->index();
            $table->unsignedSmallInteger('port')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('app_technology', function (Blueprint $table) {
            $table->foreignId('app_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();
            $table->string('version', 80)->nullable();
            $table->primary(['app_id', 'technology_id']);
        });

        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 24)->index();
            $table->string('url', 2048)->nullable();
            $table->unsignedSmallInteger('expected_status')->default(200);
            $table->unsignedSmallInteger('timeout_ms')->default(3000);
            $table->unsignedSmallInteger('interval_seconds')->default(60);
            $table->unsignedSmallInteger('failure_threshold')->default(3);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('check_states', function (Blueprint $table) {
            $table->foreignId('check_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('unknown')->index();
            $table->unsignedInteger('response_ms')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->timestamp('last_changed_at')->nullable();
            $table->string('message', 255)->nullable();
        });

        Schema::create('metric_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('unit', 24);
        });

        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('metric_type_id')->constrained()->cascadeOnDelete();
            $table->string('dimension', 255)->default('');
            $table->double('value');
            $table->timestamp('collected_at')->index();
            $table->unique(['server_id', 'metric_type_id', 'dimension'], 'server_metric_current_unique');
        });

        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('metric_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('check_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('comparison', 24);
            $table->double('threshold')->nullable();
            $table->unsignedSmallInteger('sustain_minutes')->default(0);
            $table->double('hysteresis')->default(0);
            $table->string('severity', 24)->default('warning')->index();
            $table->unsignedSmallInteger('renotify_minutes')->default(60);
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('alert_rule_states', function (Blueprint $table) {
            $table->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();
            $table->string('dimension', 255)->default('');
            $table->timestamp('breach_since')->nullable();
            $table->primary(['alert_rule_id', 'dimension']);
        });

        Schema::create('alert_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();
            $table->string('dimension', 255)->default('');
            $table->string('state', 24)->index();
            $table->string('severity', 24)->index();
            $table->double('value')->nullable();
            $table->string('message', 255);
            $table->timestamp('started_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->index(['state', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_events');
        Schema::dropIfExists('alert_rule_states');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('server_metrics');
        Schema::dropIfExists('metric_types');
        Schema::dropIfExists('check_states');
        Schema::dropIfExists('checks');
        Schema::dropIfExists('app_technology');
        Schema::dropIfExists('apps');
        Schema::dropIfExists('technologies');
        Schema::dropIfExists('services');
        Schema::dropIfExists('servers');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('personal_access_tokens');
    }
};
