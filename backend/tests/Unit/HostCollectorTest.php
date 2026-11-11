<?php

namespace Tests\Unit;

use App\Services\HostCollector;
use Tests\TestCase;

class HostCollectorTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = sys_get_temp_dir().'/panel-fixture-'.bin2hex(random_bytes(6));
        mkdir($this->fixture.'/proc', 0770, true);
        mkdir($this->fixture.'/sys/class/thermal/thermal_zone0', 0770, true);
        file_put_contents($this->fixture.'/proc/loadavg', "0.12 0.34 0.56 1/100 123\n");
        file_put_contents($this->fixture.'/proc/meminfo', "MemTotal:       1000000 kB\nMemAvailable:    250000 kB\nSwapTotal:       500000 kB\nSwapFree:        100000 kB\n");
        file_put_contents($this->fixture.'/proc/uptime', "86400.25 12345.00\n");
        file_put_contents($this->fixture.'/sys/class/thermal/thermal_zone0/temp', "65000\n");
        file_put_contents($this->fixture.'/sys/class/thermal/thermal_zone0/type', "cpu-thermal\n");
        config([
            'panel.proc_path' => $this->fixture.'/proc',
            'panel.sys_path' => $this->fixture.'/sys',
            'panel.mounts' => [],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([
            $this->fixture.'/proc/loadavg',
            $this->fixture.'/proc/meminfo',
            $this->fixture.'/proc/uptime',
            $this->fixture.'/sys/class/thermal/thermal_zone0/temp',
            $this->fixture.'/sys/class/thermal/thermal_zone0/type',
        ] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->fixture.'/sys/class/thermal/thermal_zone0');
        rmdir($this->fixture.'/sys/class/thermal');
        rmdir($this->fixture.'/sys/class');
        rmdir($this->fixture.'/sys');
        rmdir($this->fixture.'/proc');
        rmdir($this->fixture);
        parent::tearDown();
    }

    public function test_reads_metrics_from_injected_proc_and_sys_paths(): void
    {
        $metrics = app(HostCollector::class)->readHostMetrics();

        $this->assertSame(0.12, $metrics['cpu_load_1']['']);
        $this->assertSame(0.34, $metrics['cpu_load_5']['']);
        $this->assertEqualsWithDelta(75.0, $metrics['ram_used_pct'][''], 0.001);
        $this->assertEqualsWithDelta(80.0, $metrics['swap_used_pct'][''], 0.001);
        $this->assertSame(86400.25, $metrics['uptime_s']['']);
        $this->assertSame(65.0, $metrics['temp_cpu_c']['']);
        $this->assertArrayNotHasKey('disk_used_pct', $metrics);
    }
}
