<?php

namespace App\Modules\Schedule\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Schedule\Models\Schedule;
use App\Modules\Operations\Models\Appointment;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    /**
     * Retrieve aggregated calendar data for the authenticated technician.
     * Maps to the format expected by Planning.tsx frontend component.
     */
    public function getCalendarData(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            Log::info("Fetching calendar data for user {$user->id}");

            // Fetch or create schedule for user
            $schedule = Schedule::firstOrCreate([
                'user_id' => $user->id
            ], [
                'tz' => 'UTC' 
            ]);

            $timeSlots = $schedule->timeSlots()->get();
            $exceptions = $schedule->exceptions()->get();
            $appointments = Appointment::where('technician_id', $user->id)
                ->where('scheduled_start', '>=', now()->startOfWeek())
                ->where('scheduled_start', '<=', now()->endOfWeek()->addWeek())
                ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
                ->with('request')
                ->orderBy('scheduled_start')
                ->get();

            // Calcul de capacité basé sur les créneaux et rendez-vous
            $totalPossibleHours = $timeSlots->sum(function ($slot) {
                $start = \Carbon\Carbon::parse($slot->start_time);
                $end = \Carbon\Carbon::parse($slot->end_time);
                return (int) $start->diffInHours($end); // Carbon 3 : résultat fractionnaire, heures entières attendues
            });
            $totalPossibleHours = $totalPossibleHours ?: 45;
            $bookedHours = $appointments->sum(function ($appt) {
                if ($appt->scheduled_end && $appt->scheduled_start) {
                    return (int) $appt->scheduled_start->diffInHours($appt->scheduled_end);
                }
                return 2;
            });
            $capacityPercent = round(($bookedHours / $totalPossibleHours) * 100);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'schedule_id' => $schedule->id,
                    'timezone' => $schedule->tz,
                    'recurring_slots' => $timeSlots,
                    'exceptions' => $exceptions,
                    'appointments' => $appointments,
                    'insights' => [
                        'capacity_percent' => $capacityPercent,
                        'booked_hours' => $bookedHours,
                        'total_hours' => $totalPossibleHours
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to fetch calendar data: " . $e->getMessage(), [
                'exception' => $e,
                'user_id' => auth()->id()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load technician calendar'
            ], 500);
        }
    }
}
