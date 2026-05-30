<?php

namespace App\Events;

use App\Modules\Operations\Models\Quote;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuoteSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Quote $quote;
    public string $clientId;

    public function __construct(Quote $quote, string $clientId)
    {
        $this->quote = $quote;
        $this->clientId = $clientId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('quotes.' . $this->clientId)];
    }

    public function broadcastWith(): array
    {
        return [
            'quote_id' => $this->quote->id,
            'total_amount' => $this->quote->total_amount,
            'status' => $this->quote->status,
            'breakdown' => $this->quote->breakdown,
        ];
    }
}
