<?php

namespace App\Modules\Schedule\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Schedule\Requests\StoreScheduleExceptionRequest;
use App\Modules\Schedule\Models\Schedule;
use App\Modules\Schedule\Models\ScheduleException;

class ScheduleExceptionController extends Controller
{
    public function store(StoreScheduleExceptionRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();
        
        $schedule = Schedule::firstOrCreate(['user_id' => $user->id]);
        
        $validated['schedule_id'] = $schedule->id;
        
        $exception = ScheduleException::create($validated);

        return response()->json([
            'status' => 'success',
            'data' => $exception
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $schedule_id = $user->schedule->id ?? null;
        
        $exception = ScheduleException::where('schedule_id', $schedule_id)->where('id', $id)->firstOrFail();
        $exception->delete();
        
        return response()->json([
            'status' => 'success'
        ]);
    }
}
