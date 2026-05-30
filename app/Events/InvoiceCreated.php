<?php

namespace App\Events;

use App\Modules\Operations\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Invoice $invoice;
    public string $clientId;

    public function __construct(Invoice $invoice, string $clientId)
    {
        $this->invoice = $invoice;
        $this->clientId = $clientId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('quotes.' . $this->clientId)];
    }

    public function broadcastWith(): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'amount' => $this->invoice->amount,
            'status' => $this->invoice->status,
        ];
    }
}
