<?php

namespace App\Modules\Social\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageAttachmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'url'       => $this->url,
            'created_at' => $this->created_at?->toISOString() ?? $this->created_at,
        ];
    }
}
