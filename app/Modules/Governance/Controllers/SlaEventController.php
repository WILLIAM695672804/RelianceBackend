<?php

namespace App\Modules\Governance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Governance\Models\SlaEvent;
use App\Modules\Governance\Requests\StoreSlaEventRequest;
use App\Modules\Governance\Resources\SlaEventResource;
use App\Modules\Governance\Services\SlaEnforcementService;

class SlaEventController extends Controller
{
    public function store(StoreSlaEventRequest $request, SlaEnforcementService $enforcement)
    {
        $event = SlaEvent::create($request->validated());

        $penalties = $enforcement->evaluate($event);

        return response()->json([
            'data'      => new SlaEventResource($event),
            'penalties' => $penalties,
        ], 201);
    }

    public function index(string $requestId)
    {
        $events = SlaEvent::where('request_id', $requestId)
            ->orderBy('occurred_at', 'desc')
            ->get();

        return SlaEventResource::collection($events);
    }
}
