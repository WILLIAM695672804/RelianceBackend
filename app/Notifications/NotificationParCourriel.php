<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Courriel d'une notification métier (proposition, devis, candidature…).
 *
 * Ces messages partaient en texte brut, réduits à une phrase sans formule d'appel, sans signature,
 * sans lien ni explication : précisément la forme des envois automatiques anonymes que les
 * messageries classent en indésirables. Chacun porte désormais le contexte, une action vers la
 * page concernée et la raison pour laquelle le destinataire le reçoit.
 */
class NotificationParCourriel extends Notification
{
    use CourrielReliance;

    /**
     * Par type : phrase d'introduction facultative, libellé du bouton, chemin dans l'espace du
     * destinataire. Le corps de la notification est repris tel quel après l'introduction.
     */
    private const TYPES = [
        'proposal' => [null, 'Voir la proposition', '/radar'],
        'proposal_accepted' => [null, 'Suivre mon intervention', '/suivi'],
        'proposal_refused' => [null, 'Choisir un autre professionnel', '/dashboard'],
        'quote_received' => [null, 'Consulter le devis', '/suivi'],
        'technician_rejected' => [
            'Après examen, votre candidature de technicien n\'a pas été retenue pour le moment. Motif indiqué par notre équipe :',
            'Consulter mon dossier',
            '/verification',
        ],
    ];

    public function __construct(
        private readonly string $titre,
        private readonly string $corps,
        private readonly array $donnees = [],
    ) {
        $this->locale('fr');
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        [$introduction, $bouton, $chemin] = self::TYPES[$this->donnees['type'] ?? ''] ?? [null, 'Ouvrir Reliance', '/notifications'];
        $espace = $this->espace($notifiable);

        $message = $this->courriel($notifiable)->subject($this->titre . ' — Reliance');
        if ($introduction) {
            $message->line($introduction);
        }

        return $message
            ->line($this->corps)
            ->action($bouton, $espace . $chemin)
            ->line('Vous recevez ce message parce que vous avez un compte sur Reliance. Vous pouvez choisir les notifications que vous recevez par e-mail depuis la page Notifications de votre espace : ' . $espace . '/notifications');
    }
}
