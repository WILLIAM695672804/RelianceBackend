<?php

namespace App\Events\Admin;

use App\Modules\Matching\Models\ClientRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewRequestCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ClientRequest $clientRequest;

    public function __construct(ClientRequest $clientRequest)
    {
        $this->clientRequest = $clientRequest;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.dashboard')];
    }

    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->clientRequest->id,
            'title' => $this->clientRequest->title,
            'status' => $this->clientRequest->status,
            'created_at' => $this->clientRequest->created_at->toIso8601String(),
        ];
    }
}
