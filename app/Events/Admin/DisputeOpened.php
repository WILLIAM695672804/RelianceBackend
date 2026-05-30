<?php

namespace App\Events\Admin;

use App\Modules\Governance\Models\Dispute;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeOpened implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Dispute $dispute;

    public function __construct(Dispute $dispute)
    {
        $this->dispute = $dispute->load(['opener']);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.dashboard')];
    }

    public function broadcastWith(): array
    {
        return [
            'dispute_id' => $this->dispute->id,
            'reason' => $this->dispute->reason,
            'opened_by' => $this->dispute->opener->name,
            'created_at' => $this->dispute->created_at->toIso8601String(),
        ];
    }
}
