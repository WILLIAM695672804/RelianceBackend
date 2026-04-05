<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles');

        if ($request->has('role')) {
            $query->role($request->role);
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('kyc_status')) {
            if ($request->kyc_status === 'pending') {
                $query->whereHas('kycDocuments', fn($q) => $q->where('status', 'pending'));
            } elseif ($request->kyc_status === 'approved') {
                $query->whereHas('kycDocuments', fn($q) => $q->where('status', 'approved'));
            }
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($users);
    }

    public function show(string $id)
    {
        $user = User::with(['roles', 'kycDocuments', 'wallet'])->findOrFail($id);

        return response()->json(['data' => $user]);
    }

    public function toggleActive(string $id)
    {
        $user = User::findOrFail($id);
        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'message' => $user->is_active ? 'Compte activé' : 'Compte désactivé',
            'is_active' => $user->is_active,
        ]);
    }

    public function pendingTechnicians()
    {
        $users = User::role('technicien')
            ->where('verified', false)
            ->with(['roles', 'kycDocuments'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($users);
    }

    public function approveTechnician(string $id)
    {
        $user = User::role('technicien')->findOrFail($id);
        $user->verified = true;
        $user->is_active = true;
        $user->save();

        return response()->json([
            'message' => 'Technicien approuvé et activé',
            'data' => $user,
        ]);
    }

    public function rejectTechnician(Request $request, string $id)
    {
        $request->validate(['reason' => 'required|string']);

        $user = User::role('technicien')->findOrFail($id);
        $user->is_active = false;
        $user->save();

        return response()->json([
            'message' => 'Technicien rejeté',
        ]);
    }
}
