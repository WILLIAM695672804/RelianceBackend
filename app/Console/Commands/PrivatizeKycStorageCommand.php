<?php

namespace App\Console\Commands;

use App\Modules\Kyc\Models\KycDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Déplace les pièces KYC déposées sur le disque public vers le disque privé.
 * Le chemin relatif (kyc/…) est conservé : aucune écriture en base n'est nécessaire.
 * Idempotente : un fichier déjà présent sur le disque privé n'est pas recopié.
 */
class PrivatizeKycStorageCommand extends Command
{
    protected $signature = 'kyc:privatiser-stockage {--dry-run : Affiche les déplacements sans les effectuer}';
    protected $description = 'Déplace les documents KYC du disque public vers le disque privé';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $local = Storage::disk('local');
        $deplaces = $deja = $manquants = 0;

        KycDocument::query()->select(['id', 'file_path'])->chunkById(200, function ($docs) use ($public, $local, &$deplaces, &$deja, &$manquants) {
            foreach ($docs as $doc) {
                $chemin = $doc->file_path;

                if (!$public->exists($chemin)) {
                    $local->exists($chemin) ? $deja++ : $manquants++;
                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line("À déplacer : {$chemin}");
                    $deplaces++;
                    continue;
                }

                if (!$local->exists($chemin)) {
                    $local->writeStream($chemin, $public->readStream($chemin));
                }
                // Suppression uniquement après copie vérifiée.
                if ($local->size($chemin) === $public->size($chemin)) {
                    $public->delete($chemin);
                    $deplaces++;
                } else {
                    $this->error("Copie incomplète, fichier public conservé : {$chemin}");
                }
            }
        });

        $this->info("Déplacés : {$deplaces} — déjà privés : {$deja} — introuvables : {$manquants}");

        return self::SUCCESS;
    }
}
