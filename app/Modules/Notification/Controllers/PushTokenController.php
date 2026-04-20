<?php

namespace App\Modules\Notification\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Models\PushToken;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    /**
     * POST /v1/push-tokens — Register a push notification token.
     */
    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'provider' => 'sometimes|in:fcm,apns',
            'device_type' => 'nullable|in:ios,android,web',
        ]);

        $pushToken = PushToken::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'token' => $request->token,
            ],
            [
                'provider' => $request->provider ?? 'fcm',
                'device_type' => $request->device_type,
            ]
        );

        return response()->json([
            'message' => 'Token enregistré',
            'data' => $pushToken,
        ]);
    }

    /**
     * DELETE /v1/push-tokens/{id} — Remove a push token.
     */
    public function destroy(Request $request, string $id)
    {
        PushToken::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Token supprimé']);
    }
}
