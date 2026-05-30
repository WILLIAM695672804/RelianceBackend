<?php

namespace App\Events;

use App\Modules\Governance\Models\Dispute;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Dispute $dispute;

    public function __construct(Dispute $dispute)
    {
        $this->dispute = $dispute;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('disputes.' . $this->dispute->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'dispute_id' => $this->dispute->id,
            'status' => $this->dispute->status,
            'resolution' => $this->dispute->resolution,
            'resolved_at' => $this->dispute->resolved_at?->toIso8601String(),
        ];
    }
}
