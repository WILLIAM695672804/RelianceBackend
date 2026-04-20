<?php

namespace App\Modules\Operations\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'request_id'               => $this->request_id,
            'technician_id'            => $this->technician_id,
            'appointment_id'           => $this->appointment_id,
            'request'                  => $this->whenLoaded('request', fn () => [
                'id'    => $this->request->id,
                'title' => $this->request->title,
                'status' => $this->request->status,
            ]),
            'technician'               => $this->whenLoaded('technician', fn () => [
                'id'   => $this->technician->id,
                'name' => $this->technician->name,
            ]),
            'status'                   => $this->status,
            'phases'                   => $this->phases,
            'route'                    => $this->route,
            'estimated_travel_seconds' => $this->estimated_travel_seconds,
            'completion_notes'         => $this->completion_notes,
            'started_at'               => $this->started_at?->toISOString(),
            'completed_at'             => $this->completed_at?->toISOString(),
            'created_at'               => $this->created_at?->toISOString(),
        ];
    }
}
