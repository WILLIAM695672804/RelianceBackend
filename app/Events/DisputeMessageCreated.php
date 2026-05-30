<?php

namespace App\Events;

use App\Modules\Governance\Models\DisputeMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DisputeMessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DisputeMessage $disputeMessage;

    public function __construct(DisputeMessage $disputeMessage)
    {
        $this->disputeMessage = $disputeMessage->load('sender');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('disputes.' . $this->disputeMessage->dispute_id)];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->disputeMessage->id,
            'dispute_id' => $this->disputeMessage->dispute_id,
            'message' => $this->disputeMessage->message,
            'attachments' => $this->disputeMessage->attachments,
            'sender' => [
                'id' => $this->disputeMessage->sender->id,
                'name' => $this->disputeMessage->sender->name,
                'avatar_url' => $this->disputeMessage->sender->avatar_url,
            ],
            'created_at' => $this->disputeMessage->created_at->toIso8601String(),
        ];
    }
}
