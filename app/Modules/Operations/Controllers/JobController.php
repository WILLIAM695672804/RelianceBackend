<?php

namespace App\Modules\Operations\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Operations\Models\TechnicianJob;
use App\Modules\Operations\Requests\UpdateJobStatusRequest;
use App\Modules\Operations\Resources\JobResource;

class JobController extends Controller
{
    /**
     * Lister les jobs du technicien connecté.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');

        $query = TechnicianJob::where('technician_id', $user->id)
            ->with(['request', 'appointment']);

        if ($status) {
            $query->where('status', $status);
        }

        $jobs = $query->orderBy('created_at', 'desc')->paginate(15);

        return JobResource::collection($jobs);
    }

    /**
     * Créer un job à partir d'un appointment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'request_id'     => 'required|uuid|exists:client_requests,id',
            'appointment_id' => 'nullable|uuid|exists:appointments,id',
        ]);

        $user = $request->user();

        $job = TechnicianJob::create([
            'request_id'     => $request->request_id,
            'technician_id'  => $user->id,
            'appointment_id' => $request->appointment_id,
            'status'         => 'pending',
        ]);

        return new JobResource($job->load(['request', 'appointment']));
    }

    /**
     * Voir un job.
     */
    public function show(string $id)
    {
        $job = TechnicianJob::with(['request', 'technician', 'appointment'])->findOrFail($id);
        return new JobResource($job);
    }

    /**
     * Mettre à jour le statut d'un job (workflow de suivi).
     */
    public function updateStatus(UpdateJobStatusRequest $request, string $id)
    {
        $job = TechnicianJob::findOrFail($id);
        $user = $request->user();

        if ($job->technician_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validated();
        $updates = ['status' => $validated['status']];

        if ($validated['status'] === 'in_progress' && !$job->started_at) {
            $updates['started_at'] = now();
        }

        if ($validated['status'] === 'completed') {
            $updates['completed_at'] = now();
            $updates['completion_notes'] = $validated['completion_notes'] ?? null;
        }

        if (isset($validated['phases'])) {
            $updates['phases'] = $validated['phases'];
        }

        $job->update($updates);

        event(new \App\Events\JobStatusChanged($job->fresh()));

        return new JobResource($job->load(['request', 'appointment']));
    }

    /**
     * Mettre à jour les phases d'un job (suivi d'avancement pour le client).
     */
    public function updatePhases(Request $request, string $id)
    {
        $request->validate([
            'phases'          => 'required|array|min:1',
            'phases.*.title'  => 'required|string',
            'phases.*.status' => 'required|in:pending,in_progress,completed',
            'phases.*.notes'  => 'nullable|string',
        ]);

        $job = TechnicianJob::findOrFail($id);
        $user = $request->user();

        if ($job->technician_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $job->update(['phases' => $request->phases]);

        event(new \App\Events\PhasesUpdated($job->fresh()));

        return new JobResource($job);
    }

    /**
     * Voir le suivi d'un job (accessible par le client de la requête).
     */
    public function tracking(Request $request, string $id)
    {
        $job = TechnicianJob::with(['request', 'technician', 'appointment'])->findOrFail($id);
        $user = $request->user();

        // Le client de la requête ou le technicien peuvent voir le suivi
        if ($job->request->client_id !== $user->id && $job->technician_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'job_id'     => $job->id,
                'status'     => $job->status,
                'phases'     => $job->phases,
                'route'      => $job->route,
                'estimated_travel_seconds' => $job->estimated_travel_seconds,
                'started_at'    => $job->started_at,
                'completed_at'  => $job->completed_at,
                'technician' => [
                    'id'   => $job->technician->id,
                    'name' => $job->technician->name,
                ],
            ],
        ]);
    }
}
