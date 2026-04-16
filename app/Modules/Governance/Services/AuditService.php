<?php

namespace App\Modules\Governance\Services;

use App\Modules\Governance\Models\AuditLog;

class AuditService
{
    public static function log(
        string $action,
        string $objectType,
        ?string $objectId = null,
        array $details = []
    ): AuditLog {
        return AuditLog::create([
            'actor_id'    => auth()->id(),
            'object_type' => $objectType,
            'object_id'   => $objectId,
            'action'      => $action,
            'details'     => $details,
        ]);
    }
}
