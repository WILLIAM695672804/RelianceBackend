<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class RoleUpgradeController extends Controller
{
    /**
     * POST /v1/auth/request-upgrade — Client requests upgrade to technician.
     */
    public function requestUpgrade(Request $request)
    {
        $request->validate([
            'motivation' => 'required|string|min:20',
            'skills' => 'nullable|array',
            'bio' => 'nullable|string',
        ]);

        $user = $request->user();

        // Check if user is already a technician
        if ($user->hasRole('technicien')) {
            return response()->json(['message' => 'Vous êtes déjà technicien'], 422);
        }

        // Check for pending request
        $pending = DB::table('role_upgrade_requests')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            return response()->json(['message' => 'Vous avez déjà une demande en attente'], 422);
        }

        DB::table('role_upgrade_requests')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'requested_role' => 'technicien',
            'motivation' => $request->motivation,
            'skills' => json_encode($request->skills ?? []),
            'bio' => $request->bio,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Demande d\'upgrade soumise. Un administrateur va la valider.',
        ]);
    }

    /**
     * GET /v1/admin/upgrade-requests — Admin sees all upgrade requests.
     */
    public function adminIndex(Request $request)
    {
        $requests = DB::table('role_upgrade_requests')
            ->join('users', 'role_upgrade_requests.user_id', '=', 'users.id')
            ->select('role_upgrade_requests.*', 'users.name', 'users.email', 'users.avatar_url')
            ->orderBy('role_upgrade_requests.created_at', 'desc')
            ->paginate(20);

        return response()->json($requests);
    }

    /**
     * POST /v1/admin/upgrade-requests/{id}/approve — Approve upgrade.
     */
    public function approve(Request $request, string $id)
    {
        $upgradeRequest = DB::table('role_upgrade_requests')->where('id', $id)->first();

        if (!$upgradeRequest) {
            return response()->json(['message' => 'Demande introuvable'], 404);
        }

        if ($upgradeRequest->status !== 'pending') {
            return response()->json(['message' => 'Demande déjà traitée'], 422);
        }

        // Assign technician role
        $user = User::findOrFail($upgradeRequest->user_id);
        $user->assignRole('technicien');

        // Update bio if provided
        if ($upgradeRequest->bio) {
            $user->update(['bio' => $upgradeRequest->bio]);
        }

        DB::table('role_upgrade_requests')
            ->where('id', $id)
            ->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => "Utilisateur {$user->name} promu technicien",
        ]);
    }

    /**
     * POST /v1/admin/upgrade-requests/{id}/reject — Reject upgrade.
     */
    public function reject(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $upgradeRequest = DB::table('role_upgrade_requests')->where('id', $id)->first();

        if (!$upgradeRequest || $upgradeRequest->status !== 'pending') {
            return response()->json(['message' => 'Demande introuvable ou déjà traitée'], 422);
        }

        DB::table('role_upgrade_requests')
            ->where('id', $id)
            ->update([
                'status' => 'rejected',
                'reviewed_by' => $request->user()->id,
                'review_note' => $request->reason,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['message' => 'Demande rejetée']);
    }
}
