<?php

namespace App\Modules\Profile\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IdentityHubResource extends JsonResource
{
    public function toArray($request)
    {
        // Extract meta data securely
        $meta = $this->meta ?? [];
        $languages = $meta['languages'] ?? [];
        $bio = $meta['bio'] ?? '';
        
        $location = $this->locations()->where('is_default', true)->first();
        
        $zones = [];
        $lat = null;
        $lng = null;
        
        if ($location) {
            $locMeta = $location->meta ?? [];
            $zones = $locMeta['zones'] ?? [];
            
            // Si on a des géométries PostGIS, on pourrait les extraire via ST_X et ST_Y (On va le stocker plus tard)
            // Mais pour faire simple on le passe en meta le temps du debug
            $lat = $locMeta['latitude'] ?? null;
            $lng = $locMeta['longitude'] ?? null;
            $radius = $locMeta['radius'] ?? 50;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'verified' => (bool) $this->verified,
            'rating' => (float) $this->rating,
            'num_reviews' => (int) $this->num_reviews,
            'bio' => $bio,
            'languages' => $languages,
            'kyc_approved' => $this->hasApprovedKyc(), // Méthode du Model
            
            'service_perimeter' => [
                'city' => $location->city ?? null,
                'address' => $location->address ?? null,
                'zones' => $zones,
                'latitude' => $lat,
                'longitude' => $lng,
                'radius' => $radius ?? 50
            ]
        ];
    }
}
