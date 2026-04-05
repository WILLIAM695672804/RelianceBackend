<?php

namespace App\Modules\Profile\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IdentityHubResource extends JsonResource
{
    public function toArray($request)
    {
        $meta = $this->meta ?? [];
        $bio = $meta['bio'] ?? '';
        $competences = $meta['competences'] ?? [];

        $location = $this->locations()->where('is_default', true)->first();

        $zones = [];
        $lat = null;
        $lng = null;
        $radius = 50;

        if ($location) {
            $locMeta = $location->meta ?? [];
            $zones = $locMeta['zones'] ?? [];
            $lat = $locMeta['latitude'] ?? null;
            $lng = $locMeta['longitude'] ?? null;
            $radius = $locMeta['radius'] ?? 50;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_url) : null,
            'verified' => (bool) $this->verified,
            'rating' => (float) $this->rating,
            'num_reviews' => (int) $this->num_reviews,
            'bio' => $bio,
            'competences' => $competences,
            'kyc_approved' => $this->hasApprovedKyc(),

            'skills' => $this->skills->map(fn($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'slug' => $skill->slug,
                'category' => $skill->category,
                'proficiency' => $skill->pivot->proficiency,
                'hourly_rate' => $skill->pivot->hourly_rate,
                'travel_fee' => $skill->pivot->travel_fee,
            ]),

            'service_perimeter' => [
                'city' => $location->city ?? null,
                'address' => $location->address ?? null,
                'zones' => $zones,
                'latitude' => $lat,
                'longitude' => $lng,
                'radius' => $radius,
            ],
        ];
    }
}
