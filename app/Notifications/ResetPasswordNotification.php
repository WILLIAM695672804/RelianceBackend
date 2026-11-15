<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

/**
 * Courriel de réinitialisation du mot de passe, en français et au nom de Reliance.
 * L'adresse du lien reste celle définie dans AuthServiceProvider (page du frontend).
 */
class ResetPasswordNotification extends ResetPassword
{
    use CourrielReliance;

    public function __construct(#[\SensitiveParameter] $token)
    {
        parent::__construct($token);
        $this->locale('fr');
    }

    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) Config::get('auth.passwords.' . Config::get('auth.defaults.passwords') . '.expire', 60);

        return $this->courriel($notifiable)
            ->subject('Réinitialisation de votre mot de passe Reliance')
            ->line('Vous avez demandé à réinitialiser le mot de passe de votre compte Reliance.')
            ->action('Choisir un nouveau mot de passe', $this->resetUrl($notifiable))
            ->line("Ce lien est valable {$minutes} minutes et ne peut servir qu'une fois.")
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message : votre mot de passe actuel reste valable.');
    }
}
