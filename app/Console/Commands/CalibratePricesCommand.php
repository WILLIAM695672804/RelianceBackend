<?php

namespace App\Console\Commands;

use App\Modules\Matching\Services\PriceCalibrationService;
use Illuminate\Console\Command;

class CalibratePricesCommand extends Command
{
    protected $signature = 'ml:calibrate-prices';
    protected $description = 'Recalibre le prix suggéré à partir des propositions acceptées et déploie le modèle';

    public function handle(PriceCalibrationService $service): int
    {
        $modele = $service->train();
        $this->info($modele ? "Modèle {$modele->version} déployé." : 'Aucun échantillon : modèle inchangé.');

        return self::SUCCESS;
    }
}
