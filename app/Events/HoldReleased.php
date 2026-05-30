<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HoldReleased implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $userId;
    public float $amount;

    public function __construct(string $userId, float $amount)
    {
        $this->userId = $userId;
        $this->amount = $amount;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('wallet.' . $this->userId)];
    }

    public function broadcastWith(): array
    {
        return [
            'amount' => $this->amount,
            'type' => 'hold_released',
        ];
    }
}
