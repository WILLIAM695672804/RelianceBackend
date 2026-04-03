<?php

namespace App\Modules\Profile\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TechnicianSkillResource extends JsonResource
{
    public function toArray($request)
    {
        $meta = $this->pivot->meta ?? [];
        $experience = $meta['experience_years'] ?? 0;

        return [
            'id' => $this->id, // skill_id basically, since it's used via relation usually
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category,
            'proficiency' => (int) $this->pivot->proficiency, // e.g. 5 for Expert
            'experience_years' => (int) $experience, // e.g. 8
            'hourly_rate' => (float) $this->pivot->hourly_rate,
            'travel_fee' => (float) $this->pivot->travel_fee,
        ];
    }
}
