<?php

namespace App\Modules\Matching\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matching\Models\MlModel;
use App\Modules\Matching\Services\PriceCalibrationService;

class AdminMlController extends Controller
{
    /** GET /v1/admin/ml-models — Versions du modèle de prix et leurs métriques d'entraînement. */
    public function index()
    {
        $modeles = MlModel::with(['trainingLogs' => fn ($q) => $q->latest('run_at')->limit(1)])->orderByDesc('created_at')->limit(20)->get();

        return response()->json(['data' => $modeles->map(fn ($m) => [
            'id' => $m->id, 'name' => $m->name, 'version' => $m->version, 'deployed' => (bool) $m->deployed,
            'by_difficulty' => $m->metadata['by_difficulty'] ?? [], 'metrics' => $m->trainingLogs->first()?->metrics,
            'created_at' => $m->created_at?->toIso8601String(),
        ])]);
    }

    /** POST /v1/admin/ml-models/train — Lance un calibrage immédiat. */
    public function train(PriceCalibrationService $service)
    {
        $modele = $service->train();

        return response()->json(['message' => $modele ? 'Modèle déployé' : 'Aucun échantillon disponible', 'version' => $modele?->version], $modele ? 201 : 200);
    }
}
