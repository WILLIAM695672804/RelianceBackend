<?php

namespace App\Modules\Matching\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientRequestResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'description'    => $this->description,
            'status'         => $this->status,
            'category'       => $this->category ? [
                'id'   => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,
            'address'        => $this->address ? [
                'id'      => $this->address->id,
                'city'    => $this->address->city,
                'address' => $this->address->address,
            ] : null,
            'photos'         => $this->photos,
            'diagnostic_fee' => $this->diagnostic_fee,
            'analysis'       => $this->whenLoaded('analysis', function () {
                return [
                    'estimated_difficulty' => $this->analysis->estimated_difficulty,
                    'suggested_price'      => $this->analysis->suggested_price,
                    'estimated_skills'     => $this->analysis->estimated_skills,
                ];
            }),
            'proposals_count' => $this->whenCounted('proposals'),
            // Interlocuteur retenu sur la demande (technicien de la proposition acceptée).
            // Permet au front de pré-remplir l'« utilisateur concerné » lors de l'ouverture d'un litige.
            'counterpart'     => $this->whenLoaded('acceptedProposal', function () {
                return $this->acceptedProposal && $this->acceptedProposal->technician ? [
                    'id'   => $this->acceptedProposal->technician->id,
                    'name' => $this->acceptedProposal->technician->name,
                    'role' => 'technician',
                ] : null;
            }),
            'created_at'      => $this->created_at->toIso8601String(),
            'updated_at'      => $this->updated_at->toIso8601String(),
        ];
    }
}
