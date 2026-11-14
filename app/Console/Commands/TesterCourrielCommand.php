<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Éprouve la configuration SMTP en envoyant un courriel pour de bon.
 *
 * Le mot de passe SMTP est saisi une fois, en aveugle, au moment du réglage :
 * une faute de frappe ne se verrait qu'à la première inscription d'un
 * utilisateur, sous la forme d'un courriel qui n'arrive jamais. Cette commande
 * ramène ce délai à quinze secondes.
 *
 *     php artisan reliance:tester-courriel vous@exemple.com
 */
class TesterCourrielCommand extends Command
{
    protected $signature = 'reliance:tester-courriel {adresse : destinataire du message d\'essai}';

    protected $description = "Envoie un courriel d'essai pour vérifier les réglages SMTP";

    public function handle(): int
    {
        $adresse = (string) $this->argument('adresse');
        $hote = config('mail.mailers.' . config('mail.default') . '.host');
        $port = config('mail.mailers.' . config('mail.default') . '.port');
        $chiffrement = config('mail.mailers.' . config('mail.default') . '.encryption');

        $this->line("  Serveur     : {$hote}:{$port} ({$chiffrement})");
        $this->line('  Expéditeur  : ' . config('mail.from.address'));
        $this->line("  Destinataire: {$adresse}");
        $this->newLine();

        try {
            Mail::raw(
                "Essai des réglages SMTP de Reliance.\n\n"
                . "Si vous lisez ce message, l'application peut envoyer les courriels de\n"
                . "vérification d'adresse et de réinitialisation de mot de passe.\n\n"
                . 'Émis le ' . now()->format('d/m/Y à H:i') . '.',
                fn ($message) => $message->to($adresse)->subject('Reliance — essai SMTP')
            );
        } catch (\Throwable $e) {
            $this->error('Échec : ' . $e->getMessage());
            $this->newLine();
            // Les trois causes qui représentent la quasi-totalité des échecs.
            $this->line('  535 ou « Authentication failed » : MAIL_USERNAME doit être');
            $this->line('      l\'adresse entière, et non la partie avant l\'arobase.');
            $this->line('  Erreur TLS ou « unexpected response » : MAIL_ENCRYPTION doit');
            $this->line('      suivre le port — 465 en ssl, 587 en tls.');
            $this->line('  Délai dépassé : le port est filtré. Essayer  nc -vz ' . $hote . ' ' . $port);

            return self::FAILURE;
        }

        $this->info('Courriel remis au serveur SMTP sans erreur.');
        $this->line('  Vérifiez la boîte du destinataire, et les indésirables : un');
        $this->line('  enregistrement SPF absent y envoie tous les messages.');

        return self::SUCCESS;
    }
}
