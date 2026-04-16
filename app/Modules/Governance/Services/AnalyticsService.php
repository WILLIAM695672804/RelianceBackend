<?php

namespace App\Modules\Governance\Services;

use App\Modules\Governance\Models\AnalyticsEvent;

class AnalyticsService
{
    public static function track(
        string $eventName,
        ?string $userId = null,
        array $props = []
    ): AnalyticsEvent {
        return AnalyticsEvent::create([
            'user_id'     => $userId ?? auth()->id(),
            'event_name'  => $eventName,
            'event_props' => $props,
        ]);
    }
}
