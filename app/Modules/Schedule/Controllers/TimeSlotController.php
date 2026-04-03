<?php

namespace App\Modules\Schedule\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Schedule\Requests\StoreTimeSlotRequest;
use App\Modules\Schedule\Models\Schedule;
use App\Modules\Schedule\Models\TimeSlot;

class TimeSlotController extends Controller
{
    public function store(StoreTimeSlotRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();
        
        $schedule = Schedule::firstOrCreate(['user_id' => $user->id]);
        
        $validated['schedule_id'] = $schedule->id;
        
        $timeSlot = TimeSlot::create($validated);

        return response()->json([
            'status' => 'success',
            'data' => $timeSlot
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $schedule_id = $user->schedule->id ?? null;
        
        $slot = TimeSlot::where('schedule_id', $schedule_id)->where('id', $id)->firstOrFail();
        $slot->delete();
        
        return response()->json([
            'status' => 'success'
        ]);
    }
}
