<?php

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Models\NotificationSetting;
use Illuminate\Http\Request;

class NotificationSettingController extends Controller
{
    /**
     * GET /v1/notification-settings — Get user's notification preferences.
     */
    public function index(Request $request)
    {
        $settings = NotificationSetting::where('user_id', $request->user()->id)->get();

        // If no settings exist yet, return defaults
        if ($settings->isEmpty()) {
            $defaults = collect(['in_app', 'push', 'email', 'sms'])->map(fn ($channel) => [
                'channel' => $channel,
                'enabled' => true,
            ]);
            return response()->json(['data' => $defaults]);
        }

        return response()->json(['data' => $settings]);
    }

    /**
     * PUT /v1/notification-settings — Update notification preferences.
     */
    public function update(Request $request)
    {
        $request->validate([
            'in_app' => 'sometimes|boolean',
            'push' => 'sometimes|boolean',
            'email' => 'sometimes|boolean',
            'sms' => 'sometimes|boolean',
        ]);

        $userId = $request->user()->id;

        foreach (['in_app', 'push', 'email', 'sms'] as $channel) {
            if ($request->has($channel)) {
                NotificationSetting::updateOrCreate(
                    ['user_id' => $userId, 'channel' => $channel],
                    ['enabled' => $request->$channel]
                );
            }
        }

        $settings = NotificationSetting::where('user_id', $userId)->get();

        return response()->json([
            'message' => 'Préférences mises à jour',
            'data' => $settings,
        ]);
    }
}
