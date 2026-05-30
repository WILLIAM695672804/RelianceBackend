<?php

namespace App\Events;

use App\Modules\Matching\Models\Proposal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProposalRefused implements ShouldBroadcast
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
            new PrivateChannel('proposals.' . $this->proposal->request->client_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'proposal_id' => $this->proposal->id,
            'request_id' => $this->proposal->request_id,
            'technician_id' => $this->proposal->technician_id,
            'status' => 'refused',
        ];
    }
}
