<?php

namespace App\Modules\Matching\Services;

use App\Modules\Matching\Models\Proposal;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Notification\Services\NotificationService;

class ProposalExpirationService
{
    protected NotificationService $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * Expire all pending proposals past their expires_at deadline.
     * Notify the client so they can choose another professional.
     */
    public function expireOverdue(): int
    {
        $expired = Proposal::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $proposal) {
            $proposal->update(['status' => 'expired']);

            // Notify the client
            $request = $proposal->request;
            if ($request && $request->user_id) {
                $this->notifications->notifyProposalExpired(
                    $request->user_id,
                    $request->id
                );
            }

            $count++;
        }

        return $count;
    }
}
