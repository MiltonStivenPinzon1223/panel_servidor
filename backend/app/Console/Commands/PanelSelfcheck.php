<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PanelSelfcheck extends Command
{
    protected $signature = 'panel:selfcheck';

    protected $description = 'Comprueba la configuración mínima, permisos del sistema y la base de datos.';

    public function handle(): int
    {
        $ok = true;
        foreach (['proc_path', 'sys_path'] as $pathKey) {
            $path = config('panel.'.$pathKey);
            if (! is_dir($path) || ! is_readable($path)) {
                $this->error("No se puede leer {$pathKey}: {$path}");
                $ok = false;
            } else {
                $this->info("Lectura disponible: {$path}");
            }
        }
        try {
            DB::connection()->getPdo();
            $this->info('Conexión a la base de datos: correcta.');
        } catch (\PDOException $exception) {
            $this->error('No se pudo conectar a la base de datos: '.$exception->getMessage());
            $ok = false;
        }
        if (config('queue.default') !== 'sync' || config('cache.default') !== 'file' || config('session.driver') !== 'array') {
            $this->error('QUEUE_CONNECTION, CACHE_STORE o SESSION_DRIVER no cumplen la configuración requerida.');
            $ok = false;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
