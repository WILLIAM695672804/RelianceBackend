<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleUpgradeDecision implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $userId;
    public string $decision;
    public ?string $reason;

    public function __construct(string $userId, string $decision, ?string $reason = null)
    {
        $this->userId = $userId;
        $this->decision = $decision;
        $this->reason = $reason;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->userId)];
    }

    public function broadcastWith(): array
    {
        return [
            'decision' => $this->decision,
            'reason' => $this->reason,
            'type' => 'role_upgrade',
        ];
    }
}
