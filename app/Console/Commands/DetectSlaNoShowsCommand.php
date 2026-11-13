<?php

namespace App\Console\Commands;

use App\Modules\Governance\Services\SlaDetectionService;
use Illuminate\Console\Command;

class DetectSlaNoShowsCommand extends Command
{
    protected $signature = 'sla:detect-no-shows';
    protected $description = 'Constate les absences aux rendez-vous confirmés et applique les pénalités SLA';

    public function handle(SlaDetectionService $detection): int
    {
        $this->info($detection->detectNoShows() . ' absence(s) constatée(s).');

        return self::SUCCESS;
    }
}
