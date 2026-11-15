<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Courriel de vérification d'adresse, en français et au nom de Reliance.
 *
 * Le modèle par défaut de Laravel (« Verify Email Address », en anglais) est partagé par d'innombrables
 * applications ; Gmail le rapproche de messages déjà signalés comme indésirables.
 */
class VerifyEmailNotification extends VerifyEmail
{
    use CourrielReliance;

    public function __construct()
    {
        $this->locale('fr');
    }

    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) Config::get('auth.verification.expire', 60);

        return $this->courriel($notifiable)
            ->subject('Confirmez votre adresse e-mail sur Reliance')
            ->line('Merci de vous être inscrit sur Reliance. Pour activer votre compte, confirmez votre adresse e-mail.')
            ->action('Confirmer mon adresse', $this->verificationUrl($notifiable))
            ->line("Ce lien est valable {$minutes} minutes. Passé ce délai, demandez-en un nouveau depuis la page de connexion.")
            ->line('Si vous n\'avez pas créé de compte sur Reliance, ignorez ce message : aucune action n\'est nécessaire.');
    }

    /**
     * Lien direct vers la page de vérification, sans adresse encodée à l'intérieur : un lien qui en
     * contient un autre ressemble aux redirections des messages d'hameçonnage. La signature est
     * relative (chemin et paramètres, sans l'hôte) : la page la reconstitue vers l'API, quel que soit
     * l'hôte par lequel la requête arrive.
     */
    protected function verificationUrl($notifiable)
    {
        $signee = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
            false
        );
        parse_str((string) parse_url($signee, PHP_URL_QUERY), $requete);

        return rtrim((string) Config::get('app.frontend_url'), '/') . '/auth/verify-email?' . http_build_query([
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'expires' => $requete['expires'] ?? null,
            'signature' => $requete['signature'] ?? null,
        ]);
    }
}
