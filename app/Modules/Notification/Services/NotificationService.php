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

        $settings = NotificationSetting::where('user_id', $userId)
            ->pluck('enabled', 'channel');

        if (in_array('push', $channels) && ($settings['push'] ?? true)) {
            $this->sendPush($userId, $title, $body, $payload);
        }

        if (in_array('email', $channels) && ($settings['email'] ?? true)) {
            $this->sendEmail($userId, $title, $body);
        }

        return $notification;
    }

    /**
     * Send push notification via FCM (placeholder — integrate real FCM).
     */
    protected function sendPush(string $userId, string $title, string $body, array $payload): void
    {
        $tokens = PushToken::where('user_id', $userId)->get();
        // TODO: Intégrer FCM via kreait/firebase-php
        // Pour chaque token, envoyer via FCM HTTP v1 API
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
            ['in_app', 'push', 'email']
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
}
