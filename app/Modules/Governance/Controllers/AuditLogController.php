<?php

namespace App\Modules\Governance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Governance\Models\AuditLog;
use App\Modules\Governance\Resources\AuditLogResource;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('actor')->orderBy('created_at', 'desc');

        if ($request->has('actor_id')) {
            $query->forActor($request->actor_id);
        }

        if ($request->has('object_type')) {
            $query->forObject($request->object_type, $request->object_id);
        }

        if ($request->has('action')) {
            $query->forAction($request->action);
        }

        if ($request->has('from') && $request->has('to')) {
            $query->between($request->from, $request->to);
        }

        return AuditLogResource::collection($query->paginate(30));
    }

    public function show(int $id)
    {
        $log = AuditLog::with('actor')->findOrFail($id);
        return new AuditLogResource($log);
    }
}
