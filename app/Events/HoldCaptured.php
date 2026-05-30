<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HoldCaptured implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $payerId;
    public string $payeeId;
    public float $amount;
    public float $netAmount;

    public function __construct(string $payerId, string $payeeId, float $amount, float $netAmount)
    {
        $this->payerId = $payerId;
        $this->payeeId = $payeeId;
        $this->amount = $amount;
        $this->netAmount = $netAmount;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('wallet.' . $this->payerId),
            new PrivateChannel('wallet.' . $this->payeeId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'amount' => $this->amount,
            'net_amount' => $this->netAmount,
            'type' => 'hold_captured',
        ];
    }
}
