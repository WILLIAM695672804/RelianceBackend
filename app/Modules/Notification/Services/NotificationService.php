<?php

namespace App\Modules\Notification\Services;

use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\PushToken;
use App\Modules\Notification\Models\NotificationSetting;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send a notification to a user across specified channels.
     */
    public function send(string $userId, string $title, string $body, array $payload = [], array $channels = ['in_app']): Notification
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'payload' => $payload,
            'is_read' => false,
        ]);

        event(new \App\Events\NotificationCreated($notification));

        $settings = NotificationSetting::where('user_id', $userId)
            ->pluck('enabled', 'channel');

        if (in_array('push', $channels) && ($settings['push'] ?? true)) {
            $this->sendPush($userId, $title, $body, $payload);
        }

        if (in_array('email', $channels) && ($settings['email'] ?? true)) {
            $this->sendEmail($userId, $title, $body);
        }

        if (in_array('sms', $channels) && ($settings['sms'] ?? true)) {
            $this->sendSms($userId, $title, $body);
        }

        return $notification;
    }

    /**
     * Notification push : jetons Expo (application mobile) via l'API Expo Push ;
     * les jetons FCM/APNs natifs sont journalisés tant qu'aucun fournisseur direct n'est configuré.
     */
    protected function sendPush(string $userId, string $title, string $body, array $payload): void
    {
        $tokens = PushToken::where('user_id', $userId)->pluck('token');

        $messages = $tokens
            ->filter(fn ($t) => str_starts_with($t, 'ExponentPushToken[') || str_starts_with($t, 'ExpoPushToken['))
            ->map(fn ($t) => ['to' => $t, 'title' => $title, 'body' => $body, 'data' => $payload, 'sound' => 'default'])
            ->values()
            ->all();

        if ($messages === []) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Http::timeout(5)
                ->acceptJson()
                ->post(config('services.expo_push.url', 'https://exp.host/--/api/v2/push/send'), $messages);
        } catch (\Throwable $e) {
            \Log::warning("Notification push échouée pour l'utilisateur {$userId} : " . $e->getMessage());
        }
    }

    /**
     * Notification SMS via {@see SmsService} (no-op silencieux sans numéro).
     */
    protected function sendSms(string $userId, string $title, string $body): void
    {
        $user = User::find($userId);
        if ($user && $user->phone) {
            app(SmsService::class)->send($user->phone, "{$title} — {$body}");
        }
    }

    /**
     * Send email notification.
     */
    protected function sendEmail(string $userId, string $title, string $body): void
    {
        $user = User::find($userId);
        if ($user && $user->email) {
            try {
                Mail::raw($body, function ($message) use ($user, $title) {
                    $message->to($user->email)->subject($title);
                });
            } catch (\Exception $e) {
                \Log::warning("Notification email failed for user {$userId}: " . $e->getMessage());
            }
        }
    }

    /**
     * Notify a technician about a new proposal.
     */
    public function notifyProposal(string $technicianId, string $requestTitle, string $proposalId): Notification
    {
        return $this->send(
            $technicianId,
            'Nouvelle proposition de mission',
            "Vous avez reçu une proposition pour : {$requestTitle}",
            ['type' => 'proposal', 'proposal_id' => $proposalId],
            ['in_app', 'push', 'email', 'sms']
        );
    }

    /**
     * Notify client that a proposal was accepted.
     */
    public function notifyProposalAccepted(string $clientId, string $technicianName, string $requestId): Notification
    {
        return $this->send(
            $clientId,
            'Proposition acceptée',
            "{$technicianName} a accepté votre demande d'intervention.",
            ['type' => 'proposal_accepted', 'request_id' => $requestId],
            ['in_app', 'push', 'email']
        );
    }

    /**
     * Notify client that a proposal has expired.
     */
    public function notifyProposalExpired(string $clientId, string $requestId): Notification
    {
        return $this->send(
            $clientId,
            'Proposition expirée',
            "Le délai d'acceptation pour votre demande a expiré. Veuillez choisir un autre professionnel.",
            ['type' => 'proposal_expired', 'request_id' => $requestId],
            ['in_app', 'push']
        );
    }

    /**
     * Notify client that a technician refused the proposal.
     */
    public function notifyProposalRefused(string $clientId, string $technicianName, string $requestId): Notification
    {
        return $this->send(
            $clientId,
            'Proposition refusée',
            "{$technicianName} ne peut pas intervenir. Veuillez choisir un autre professionnel.",
            ['type' => 'proposal_refused', 'request_id' => $requestId],
            ['in_app', 'push', 'email']
        );
    }

    /**
     * Notify client that a quote was sent to them.
     */
    public function notifyQuoteReceived(string $clientId, string $quoteId, string $requestId): Notification
    {
        return $this->send(
            $clientId,
            'Nouveau devis reçu',
            'Le technicien vous a envoyé un devis à valider.',
            ['type' => 'quote_received', 'quote_id' => $quoteId, 'request_id' => $requestId],
            ['in_app', 'push', 'email']
        );
    }

    /**
     * Notify client of a job progress change.
     */
    public function notifyJobStatus(string $clientId, string $status, string $requestId): Notification
    {
        $labels = [
            'en_route' => 'Le technicien est en route.',
            'in_progress' => "L'intervention a démarré.",
            'completed' => "L'intervention est terminée : réglez la facture et laissez votre avis.",
            'cancelled' => "L'intervention a été annulée.",
        ];

        return $this->send(
            $clientId,
            'Suivi de votre intervention',
            $labels[$status] ?? "Statut de l'intervention : {$status}.",
            ['type' => 'job_status', 'status' => $status, 'request_id' => $requestId],
            ['in_app', 'push']
        );
    }
}
