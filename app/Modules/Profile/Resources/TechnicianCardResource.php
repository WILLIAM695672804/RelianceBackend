<?php

namespace App\Modules\Profile\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TechnicianCardResource extends JsonResource
{
    public function toArray($request)
    {
        $meta = $this->meta ?? [];
        $location = $this->locations()->where('is_default', true)->first();
        $locMeta = $location ? ($location->meta ?? []) : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url ? Storage::disk('public')->url($this->avatar_url) : null,
            'verified' => (bool) $this->verified,
            'rating' => (float) $this->rating,
            'num_reviews' => (int) $this->num_reviews,
            'bio' => $meta['bio'] ?? '',
            'kyc_approved' => $this->hasApprovedKyc(),
            'skills' => $this->skills->map(fn($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'slug' => $skill->slug,
                'category' => $skill->category,
                'proficiency' => $skill->pivot->proficiency,
                'hourly_rate' => $skill->pivot->hourly_rate,
            ]),
            'location' => [
                'city' => $location->city ?? null,
                'latitude' => $locMeta['latitude'] ?? null,
                'longitude' => $locMeta['longitude'] ?? null,
                'radius' => $locMeta['radius'] ?? 50,
            ],
            'distance_km' => $this->distance_km ?? null,
        ];
    }
}
