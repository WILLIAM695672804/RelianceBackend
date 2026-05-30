<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KycStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $userId;
    public string $status;
    public ?string $rejectionReason;

    public function __construct(string $userId, string $status, ?string $rejectionReason = null)
    {
        $this->userId = $userId;
        $this->status = $status;
        $this->rejectionReason = $rejectionReason;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->userId)];
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->status,
            'rejection_reason' => $this->rejectionReason,
            'type' => 'kyc_status',
        ];
    }
}
