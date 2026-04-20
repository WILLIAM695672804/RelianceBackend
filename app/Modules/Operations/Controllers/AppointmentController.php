<?php

namespace App\Modules\Operations\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Operations\Models\Appointment;
use App\Modules\Operations\Requests\StoreAppointmentRequest;
use App\Modules\Operations\Resources\AppointmentResource;

class AppointmentController extends Controller
{
    /**
     * Lister les rendez-vous du technicien connecté.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $appointments = Appointment::where('technician_id', $user->id)
            ->with(['request', 'location'])
            ->orderBy('scheduled_start', 'asc')
            ->paginate(20);

        return AppointmentResource::collection($appointments);
    }

    /**
     * Lister les rendez-vous à venir (pour le calendrier du technicien).
     */
    public function upcoming(Request $request)
    {
        $user = $request->user();

        $appointments = Appointment::where('technician_id', $user->id)
            ->where('scheduled_start', '>=', now())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->with(['request', 'location'])
            ->orderBy('scheduled_start', 'asc')
            ->limit(10)
            ->get();

        return AppointmentResource::collection($appointments);
    }

    /**
     * Créer un rendez-vous.
     */
    public function store(StoreAppointmentRequest $request)
    {
        $validated = $request->validated();

        $appointment = Appointment::create($validated);

        return new AppointmentResource($appointment->load(['request', 'location']));
    }

    /**
     * Voir un rendez-vous.
     */
    public function show(string $id)
    {
        $appointment = Appointment::with(['request', 'technician', 'location', 'job'])->findOrFail($id);
        return new AppointmentResource($appointment);
    }

    /**
     * Mettre à jour le statut d'un rendez-vous.
     */
    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|in:confirmed,in_progress,completed,cancelled,no_show',
        ]);

        $appointment = Appointment::findOrFail($id);
        $user = $request->user();

        if ($appointment->technician_id !== $user->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $appointment->update(['status' => $request->status]);

        return new AppointmentResource($appointment);
    }
}
