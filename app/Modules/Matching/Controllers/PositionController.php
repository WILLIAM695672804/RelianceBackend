<?php

namespace App\Modules\Matching\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Matching\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PositionController extends Controller
{
    /**
     * Technician updates their live GPS position (called every ~10s).
     */
    public function update(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $key = "live_position_{$request->user()->id}";
        Cache::put($key, [
            'latitude'   => $request->latitude,
            'longitude'  => $request->longitude,
            'updated_at' => now()->toIso8601String(),
        ], now()->addMinutes(5));

        return response()->json(['status' => 'ok']);
    }

    /**
     * Client fetches the technician's live position for their active mission.
     */
    public function getTechnicianPosition(Request $request)
    {
        // Find the active mission for this client
        $proposal = Proposal::whereHas('request', function ($q) use ($request) {
                $q->where('client_id', $request->user()->id);
            })
            ->whereIn('status', ['pending', 'accepted'])
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$proposal) {
            return response()->json(['status' => 'success', 'data' => null]);
        }

        $position = Cache::get("live_position_{$proposal->technician_id}");

        return response()->json([
            'status' => 'success',
            'data'   => $position,
        ]);
    }
}
