<?php

namespace App\Console\Commands;

use App\Modules\Kyc\Models\KycDocument;
use App\Modules\Social\Models\MessageAttachment;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Déplace vers le disque privé les fichiers confidentiels déposés sur le disque public :
 * pièces KYC et pièces jointes de messagerie. Le chemin relatif est conservé : aucune
 * écriture en base n'est nécessaire. Idempotente.
 */
class PrivatizeStorageCommand extends Command
{
    protected $signature = 'stockage:privatiser {--dry-run : Affiche les déplacements sans les effectuer}';
    protected $description = 'Déplace les pièces KYC et les pièces jointes du disque public vers le disque privé';
    protected $aliases = ['kyc:privatiser-stockage'];

    /** Modèles dont la colonne file_path désigne un fichier confidentiel. */
    public const MODELES = [KycDocument::class, MessageAttachment::class];

    public function handle(): int
    {
        $public = Storage::disk('public');
        $local = Storage::disk('local');

        foreach (self::MODELES as $modele) {
            $bilan = ['deplaces' => 0, 'deja' => 0, 'manquants' => 0];

            $modele::query()->select(['id', 'file_path'])->chunkById(200, function ($lignes) use ($public, $local, &$bilan) {
                foreach ($lignes as $ligne) {
                    $this->deplacer($ligne->file_path, $public, $local, $bilan);
                }
            });

            $this->info(class_basename($modele) . " — déplacés : {$bilan['deplaces']}, déjà privés : {$bilan['deja']}, introuvables : {$bilan['manquants']}");
        }

        return self::SUCCESS;
    }

    private function deplacer(string $chemin, Filesystem $public, Filesystem $local, array &$bilan): void
    {
        if (!$public->exists($chemin)) {
            $local->exists($chemin) ? $bilan['deja']++ : $bilan['manquants']++;
            return;
        }

        if ($this->option('dry-run')) {
            $this->line("À déplacer : {$chemin}");
            $bilan['deplaces']++;
            return;
        }

        if (!$local->exists($chemin)) {
            $local->writeStream($chemin, $public->readStream($chemin));
        }
        // Suppression uniquement après copie vérifiée.
        if ($local->size($chemin) === $public->size($chemin)) {
            $public->delete($chemin);
            $bilan['deplaces']++;
        } else {
            $this->error("Copie incomplète, fichier public conservé : {$chemin}");
        }
    }
}
