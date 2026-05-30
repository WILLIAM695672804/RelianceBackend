<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BalanceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $userId;
    public float $balance;
    public string $reason;

    public function __construct(string $userId, float $balance, string $reason)
    {
        $this->userId = $userId;
        $this->balance = $balance;
        $this->reason = $reason;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('wallet.' . $this->userId)];
    }

    public function broadcastWith(): array
    {
        return [
            'balance' => $this->balance,
            'reason' => $this->reason,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
