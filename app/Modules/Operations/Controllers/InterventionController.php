<?php

namespace App\Modules\Operations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Models\Intervention;
use Illuminate\Http\Request;

class InterventionController extends Controller
{
    public function index(Request $request)
    {
        $query = Intervention::with(['technician', 'request']);

        if ($request->user()->hasRole('technician') || $request->user()->hasRole('technicien')) {
            $query->where('technician_id', $request->user()->id);
        }

        return response()->json([
            'data' => $query->orderBy('scheduled_at', 'desc')->paginate(20),
        ]);
    }

    public function show(string $id)
    {
        $intervention = Intervention::with(['technician', 'request', 'job'])->findOrFail($id);
        return response()->json(['data' => $intervention]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'job_id'       => 'required|uuid|exists:technician_jobs,id',
            'request_id'   => 'required|uuid|exists:client_requests,id',
            'type'         => 'sometimes|string|in:standard,urgence,suivi',
            'description'  => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $intervention = Intervention::create([
            'job_id'        => $request->job_id,
            'technician_id' => $request->user()->id,
            'request_id'    => $request->request_id,
            'type'          => $request->input('type', 'standard'),
            'status'        => 'planned',
            'description'   => $request->description,
            'scheduled_at'  => $request->scheduled_at,
        ]);

        return response()->json(['data' => $intervention], 201);
    }

    public function start(string $id, Request $request)
    {
        $intervention = Intervention::where('technician_id', $request->user()->id)->findOrFail($id);
        $intervention->update(['status' => 'in_progress', 'started_at' => now()]);
        return response()->json(['data' => $intervention]);
    }

    public function complete(string $id, Request $request)
    {
        $request->validate([
            'work_performed' => 'required|string',
            'materials_used' => 'nullable|array',
            'duration_hours' => 'nullable|numeric|min:0',
            'notes'          => 'nullable|string',
        ]);

        $intervention = Intervention::where('technician_id', $request->user()->id)->findOrFail($id);
        $intervention->update([
            'status'         => 'completed',
            'work_performed' => $request->work_performed,
            'materials_used' => $request->materials_used,
            'duration_hours' => $request->duration_hours,
            'notes'          => $request->notes,
            'completed_at'   => now(),
        ]);

        return response()->json(['data' => $intervention]);
    }
}
