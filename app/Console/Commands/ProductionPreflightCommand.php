<?php

namespace App\Console\Commands;

use App\Modules\Kyc\Models\KycDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Contrôle préalable au déploiement : à exécuter sur le serveur cible, après `config:cache`.
 * Code de sortie 1 si une erreur bloquante est relevée ; les avertissements ne bloquent pas.
 */
class ProductionPreflightCommand extends Command
{
    protected $signature = 'reliance:verifier-production';
    protected $description = 'Vérifie que la configuration est apte à la mise en production';

    private array $erreurs = [];
    private array $avertissements = [];

    public function handle(): int
    {
        $this->verifierApplication();
        $this->verifierSessionEtCors();
        $this->verifierServicesExternes();
        $this->verifierBaseDeDonnees();
        $this->verifierStockage();

        foreach ($this->avertissements as $message) {
            $this->warn("  ! {$message}");
        }
        foreach ($this->erreurs as $message) {
            $this->error("  ✗ {$message}");
        }

        if ($this->erreurs) {
            $this->error(count($this->erreurs) . ' erreur(s) bloquante(s) : déploiement refusé.');
            return self::FAILURE;
        }

        $this->info('Configuration apte à la production (' . count($this->avertissements) . ' avertissement(s)).');
        return self::SUCCESS;
    }

    private function verifierApplication(): void
    {
        $this->exiger(app()->environment('production'), 'APP_ENV doit valoir « production ».');
        $this->exiger(!config('app.debug'), 'APP_DEBUG doit valoir false (traces et secrets exposés sinon).');
        $this->exiger(!empty(config('app.key')), 'APP_KEY est absente.');
        $this->exiger(Str::startsWith((string) config('app.url'), 'https://'), 'APP_URL doit être en https.');
        $this->exiger(
            !in_array('debug', $this->niveauxDeJournalisation(), true),
            'LOG_LEVEL ne doit pas valoir « debug » (journaux volumineux, données personnelles).'
        );
    }

    private function verifierSessionEtCors(): void
    {
        $frontend = (string) config('app.frontend_url', '');
        $this->exiger(Str::startsWith($frontend, 'https://'), 'FRONTEND_URL doit être définie et en https.');
        $this->exiger((bool) config('session.secure'), 'SESSION_SECURE_COOKIE doit valoir true.');

        $stateful = (array) config('sanctum.stateful', []);
        $locaux = array_filter($stateful, fn ($d) => Str::contains($d, ['localhost', '127.0.0.1', '.local', '192.168.']));
        $this->exiger(!$locaux, 'SANCTUM_STATEFUL_DOMAINS contient des domaines de développement : ' . implode(', ', $locaux));
    }

    private function verifierServicesExternes(): void
    {
        $this->exiger(!empty(config('services.notchpay.private_key')), 'NOTCHPAY_PRIVATE_KEY est absente.');
        $this->exiger(!empty(config('services.notchpay.hash_key')), 'NOTCHPAY_HASH_KEY est absente (webhooks refusés).');

        $mailer = config('mail.default');
        $hote = (string) config("mail.mailers.{$mailer}.host", '');
        $this->exiger(!in_array($mailer, ['log', 'array'], true), "MAIL_MAILER « {$mailer} » n'envoie aucun courriel.");
        $this->exiger(!Str::contains($hote, ['mailtrap', 'mailpit', 'sandbox']), "MAIL_HOST « {$hote} » est un bac à sable.");

        $this->signaler(config('services.sms.driver') !== 'log', 'SMS_DRIVER vaut « log » : aucun SMS ne sera émis.');
        $this->signaler((bool) config('reliance.require_2fa_clients'), 'REQUIRE_2FA_CLIENTS est désactivé (2FA non exigée pour les actions financières).');
        $this->signaler(config('broadcasting.connections.pusher.options.scheme') === 'https', 'PUSHER_SCHEME n\'est pas https (WebSocket en clair).');
        $this->signaler(!empty(config('reliance.trusted_proxies')), 'TRUSTED_PROXIES est vide : à renseigner si un proxy inverse précède l\'application.');
    }

    private function verifierBaseDeDonnees(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->erreurs[] = 'Base de données injoignable : ' . $e->getMessage();
            return;
        }

        $this->exiger(DB::getDriverName() === 'pgsql', 'La base doit être PostgreSQL (PostGIS requis).');
        if (DB::getDriverName() === 'pgsql') {
            $presentes = collect(DB::select('SELECT extname FROM pg_extension'))->pluck('extname')->all();
            // Seule PostGIS est requise (géolocalisation du rapprochement) ; gen_random_uuid() est native depuis PostgreSQL 13.
            $this->exiger(in_array('postgis', $presentes, true), 'Extension PostgreSQL « postgis » absente.');
        }

        $migrator = app('migrator');
        $fichiers = $migrator->getMigrationFiles($migrator->paths() ?: [database_path('migrations')]);
        $jouees = $migrator->getRepository()->repositoryExists() ? $migrator->getRepository()->getRan() : [];
        $enAttente = array_diff(array_keys($fichiers), $jouees);
        $this->signaler(!$enAttente, count($enAttente) . ' migration(s) en attente : exécuter `php artisan migrate --force`.');
    }

    private function verifierStockage(): void
    {
        try {
            $exposees = KycDocument::query()->pluck('file_path')
                ->filter(fn ($chemin) => Storage::disk('public')->exists($chemin))->count();
        } catch (\Throwable) {
            return; // table absente : signalé par les migrations en attente
        }
        $this->exiger($exposees === 0, "{$exposees} pièce(s) KYC encore sur le disque public : exécuter `php artisan kyc:privatiser-stockage`.");
    }

    /** @return string[] niveaux effectifs du canal par défaut (ou de chacun des canaux d'un « stack ») */
    private function niveauxDeJournalisation(): array
    {
        $defaut = config('logging.default');
        $canaux = config("logging.channels.{$defaut}.driver") === 'stack'
            ? config("logging.channels.{$defaut}.channels", [])
            : [$defaut];

        return collect($canaux)->map(fn ($canal) => config("logging.channels.{$canal}.level", 'debug'))->all();
    }

    private function exiger(bool $condition, string $message): void
    {
        if (!$condition) {
            $this->erreurs[] = $message;
        }
    }

    private function signaler(bool $condition, string $message): void
    {
        if (!$condition) {
            $this->avertissements[] = $message;
        }
    }
}
