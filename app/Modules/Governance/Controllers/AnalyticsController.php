<?php

namespace App\Modules\Governance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Governance\Models\AnalyticsEvent;
use App\Modules\Governance\Resources\AnalyticsEventResource;
use App\Modules\Governance\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $query = AnalyticsEvent::query()->orderBy('created_at', 'desc');

        if ($request->has('event_name')) {
            $query->forEvent($request->event_name);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('from') && $request->has('to')) {
            $query->between($request->from, $request->to);
        }

        return AnalyticsEventResource::collection($query->paginate(30));
    }

    public function summary(Request $request)
    {
        $days = $request->input('days', 30);
        $since = now()->subDays($days);

        $topEvents = AnalyticsEvent::where('created_at', '>=', $since)
            ->select('event_name', DB::raw('COUNT(*) as count'))
            ->groupBy('event_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $activeUsers = AnalyticsEvent::where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');

        $eventsPerDay = AnalyticsEvent::where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'period_days'    => $days,
            'top_events'     => $topEvents,
            'active_users'   => $activeUsers,
            'events_per_day' => $eventsPerDay,
        ]);
    }

    public function track(Request $request)
    {
        $request->validate([
            'event_name'  => ['required', 'string'],
            'event_props' => ['nullable', 'array'],
        ]);

        $event = AnalyticsService::track(
            $request->event_name,
            $request->user()?->id,
            $request->event_props ?? []
        );

        return new AnalyticsEventResource($event);
    }
}
