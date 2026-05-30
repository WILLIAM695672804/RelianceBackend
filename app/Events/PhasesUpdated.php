<?php

namespace App\Events;

use App\Modules\Operations\Models\TechnicianJob;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhasesUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public TechnicianJob $job;

    public function __construct(TechnicianJob $job)
    {
        $this->job = $job;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('jobs.' . $this->job->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->job->id,
            'phases' => $this->job->phases,
        ];
    }
}
