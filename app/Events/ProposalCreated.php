<?php

namespace App\Events;

use App\Modules\Matching\Models\Proposal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Proposal $proposal;

    public function __construct(Proposal $proposal)
    {
        $this->proposal = $proposal->load(['request']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('proposals.' . $this->proposal->technician_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->proposal->id,
            'request_id' => $this->proposal->request_id,
            'price' => $this->proposal->price,
            'message' => $this->proposal->message,
            'expires_at' => $this->proposal->expires_at?->toIso8601String(),
            'request' => [
                'id' => $this->proposal->request->id,
                'title' => $this->proposal->request->title,
                'description' => $this->proposal->request->description,
            ],
        ];
    }
}
