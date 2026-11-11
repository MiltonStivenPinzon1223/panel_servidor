<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevokePanelTokens extends Command
{
    protected $signature = 'panel:tokens:revoke-all';

    protected $description = 'Revoca todos los tokens de acceso de la API.';

    public function handle(): int
    {
        $count = DB::table('personal_access_tokens')->delete();
        $this->info("Se revocaron {$count} tokens.");

        return self::SUCCESS;
    }
}
