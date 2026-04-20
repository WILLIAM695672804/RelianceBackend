<?php

namespace App\Modules\Operations\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'request_id'      => $this->request_id,
            'technician_id'   => $this->technician_id,
            'request'         => $this->whenLoaded('request', fn () => [
                'id'    => $this->request->id,
                'title' => $this->request->title,
                'status' => $this->request->status,
            ]),
            'technician'      => $this->whenLoaded('technician', fn () => [
                'id'   => $this->technician->id,
                'name' => $this->technician->name,
            ]),
            'location'        => $this->whenLoaded('location', fn () => [
                'id'      => $this->location->id,
                'address' => $this->location->address,
                'city'    => $this->location->city,
            ]),
            'scheduled_start' => $this->scheduled_start?->toISOString(),
            'scheduled_end'   => $this->scheduled_end?->toISOString(),
            'status'          => $this->status,
            'notes'           => $this->notes,
            'assigned_at'     => $this->assigned_at?->toISOString(),
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
