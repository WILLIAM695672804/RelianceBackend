<?php

namespace App\Modules\Matching\Services;

use App\Modules\Matching\Models\MlModel;
use App\Modules\Matching\Models\MlTrainingLog;
use App\Modules\Matching\Models\Proposal;
use Illuminate\Support\Facades\DB;

/**
 * Calibrage du prix suggéré à partir des prix réellement acceptés (médiane par niveau de difficulté).
 * Chaque entraînement est versionné (ml_models) et journalisé (ml_training_logs) avec l'erreur absolue
 * moyenne du modèle comparée à celle des valeurs par défaut, sur le même jeu de données.
 */
class PriceCalibrationService
{
    public const MODEL = 'price_estimator';
    public const MIN_ECHANTILLONS = 3;

    public const DEFAUTS = ['simple' => 5000.0, 'moderate' => 15000.0, 'complex' => 35000.0];

    /** Prix suggéré : médiane apprise si le modèle déployé a assez d'échantillons, sinon valeur par défaut. */
    public function estimate(string $difficulty): float
    {
        $modele = MlModel::where('name', self::MODEL)->where('deployed', true)->latest('created_at')->first();
        $appris = $modele?->metadata['by_difficulty'][$difficulty] ?? null;

        if ($appris && ($appris['samples'] ?? 0) >= self::MIN_ECHANTILLONS) {
            return (float) $appris['median'];
        }

        return self::DEFAUTS[$difficulty] ?? self::DEFAUTS['moderate'];
    }

    public function deployedVersion(): ?string
    {
        return MlModel::where('name', self::MODEL)->where('deployed', true)->latest('created_at')->value('version');
    }

    /** Entraîne et déploie une nouvelle version ; null s'il n'existe aucun échantillon. */
    public function train(): ?MlModel
    {
        $echantillons = Proposal::query()
            ->where('proposals.status', 'accepted')
            ->join('request_analyses as ra', 'ra.request_id', '=', 'proposals.request_id')
            ->whereNotNull('ra.estimated_difficulty')
            ->where('proposals.price', '>', 0)
            ->get(['ra.estimated_difficulty as difficulte', 'proposals.price as prix'])
            ->groupBy('difficulte');

        if ($echantillons->isEmpty()) {
            return null;
        }

        $parDifficulte = [];
        $erreurModele = $erreurDefaut = $total = 0.0;
        foreach ($echantillons as $difficulte => $lignes) {
            $prix = $lignes->pluck('prix')->map(fn ($p) => (float) $p)->sort()->values();
            $n = $prix->count();
            $mediane = $n % 2 ? $prix[intdiv($n, 2)] : ($prix[$n / 2 - 1] + $prix[$n / 2]) / 2;
            $parDifficulte[$difficulte] = ['median' => round($mediane, 2), 'samples' => $n];

            $retenue = $n >= self::MIN_ECHANTILLONS ? $mediane : (self::DEFAUTS[$difficulte] ?? self::DEFAUTS['moderate']);
            foreach ($prix as $p) {
                $erreurModele += abs($p - $retenue);
                $erreurDefaut += abs($p - (self::DEFAUTS[$difficulte] ?? self::DEFAUTS['moderate']));
                $total++;
            }
        }

        return DB::transaction(function () use ($parDifficulte, $erreurModele, $erreurDefaut, $total) {
            MlModel::where('name', self::MODEL)->update(['deployed' => false]);
            $modele = MlModel::create([
                'name' => self::MODEL,
                'version' => now()->format('YmdHis'),
                'model_ref' => 'median_by_difficulty',
                'metadata' => ['by_difficulty' => $parDifficulte, 'min_samples' => self::MIN_ECHANTILLONS],
                'deployed' => true,
            ]);
            MlTrainingLog::create([
                'model_id' => $modele->id,
                'dataset_ref' => 'proposals(status=accepted) ⨝ request_analyses',
                'metrics' => [
                    'samples' => (int) $total,
                    'mae_model' => round($erreurModele / $total, 2),
                    'mae_default' => round($erreurDefaut / $total, 2),
                ],
                'run_at' => now(),
                'notes' => 'Calibrage par médiane (difficulté).',
            ]);

            return $modele;
        });
    }
}
