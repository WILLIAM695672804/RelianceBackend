<?php

namespace App\Modules\Matching\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Requests\StoreClientRequestRequest;
use App\Modules\Matching\Resources\ClientRequestResource;
use App\Modules\Matching\Services\RequestAnalysisService;
use App\Modules\Matching\Services\MatchingService;
use App\Modules\Profile\Models\Location;
use App\Modules\Profile\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientRequestController extends Controller
{
    public function store(StoreClientRequestRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();

        return DB::transaction(function () use ($validated, $user, $request) {
            // Resolve or create address
            $addressId = $validated['address_id'] ?? null;

            if (!$addressId && isset($validated['latitude'], $validated['longitude'])) {
                $location = Location::create([
                    'user_id' => $user->id,
                    'label'   => $validated['address_label'] ?? 'Adresse de la demande',
                    'city'    => $validated['city'] ?? null,
                    'address' => $validated['address_label'] ?? null,
                ]);

                DB::statement(
                    "UPDATE locations SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?",
                    [$validated['longitude'], $validated['latitude'], $location->id]
                );

                $location->meta = [
                    'latitude'  => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                ];
                $location->save();

                $addressId = $location->id;
            }

            // Resolve category
            $categoryId = null;
            if (isset($validated['category_slug'])) {
                $skill      = Skill::where('slug', $validated['category_slug'])->first();
                $categoryId = $skill?->id;
            }

            // Handle photo uploads
            $photoPaths = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $photoPaths[] = $photo->store('requests/photos', 'public');
                }
            }

            // Create the request
            $clientRequest = ClientRequest::create([
                'client_id'         => $user->id,
                'title'             => $validated['title'],
                'description'       => $validated['description'],
                'address_id'        => $addressId,
                'detected_category' => $categoryId,
                'status'            => 'new',
                'photos'            => $photoPaths,
            ]);

            // Auto-analyze
            $analysisService = new RequestAnalysisService();
            $analysisService->analyze($clientRequest);

            $clientRequest->load(['analysis', 'category', 'address']);

            event(new \App\Events\Admin\NewRequestCreated($clientRequest));

            return response()->json([
                'status'  => 'success',
                'message' => 'Demande créée et analysée',
                'data'    => new ClientRequestResource($clientRequest),
            ], 201);
        });
    }

    public function index(Request $request)
    {
        $requests = ClientRequest::where('client_id', $request->user()->id)
            ->with(['analysis', 'category', 'address', 'acceptedProposal.technician'])
            ->withCount('proposals')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return ClientRequestResource::collection($requests);
    }

    public function show(Request $request, string $id)
    {
        $clientRequest = ClientRequest::where('client_id', $request->user()->id)
            ->with(['analysis', 'category', 'address', 'proposals.technician', 'acceptedProposal.technician'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => new ClientRequestResource($clientRequest),
        ]);
    }

    public function match(string $id)
    {
        $clientRequest = ClientRequest::where('client_id', auth()->id())
            ->with(['address', 'category'])
            ->findOrFail($id);

        $matchingService = new MatchingService();
        $result          = $matchingService->match($clientRequest);

        // Update request status
        if (!empty($result['technicians'])) {
            $clientRequest->status = 'analyzed';
            $clientRequest->save();
        }

        return response()->json([
            'status' => 'success',
            'data'   => $result,
        ]);
    }
}
