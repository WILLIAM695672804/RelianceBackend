<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Socle commun des courriels de Reliance : formule d'appel au prénom et signature de l'équipe.
 * Un message personnalisé et signé se distingue des envois automatiques anonymes que les
 * messageries classent en indésirables.
 */
trait CourrielReliance
{
    protected function courriel($notifiable): MailMessage
    {
        $prenom = trim((string) strtok((string) ($notifiable->name ?? ''), ' '));

        return (new MailMessage)
            ->greeting($prenom !== '' ? "Bonjour {$prenom}," : 'Bonjour,')
            ->salutation('Cordialement, l\'équipe Reliance');
    }

    /** Racine de l'espace du destinataire dans l'interface web, selon son rôle. */
    protected function espace($notifiable): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $role = method_exists($notifiable, 'getRoleNames') ? $notifiable->getRoleNames()->first() : null;

        return $base . match ($role) {
            'technicien' => '/technicien',
            'agence' => '/agence',
            'admin' => '/admin',
            default => '/espace-client',
        };
    }
}
