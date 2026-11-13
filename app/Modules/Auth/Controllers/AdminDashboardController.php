<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Kyc\Models\KycDocument;
use App\Modules\Matching\Models\ClientRequest;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function stats()
    {
        $totalUsers = User::count();
        $totalClients = User::role('client')->count();
        $totalTechnicians = User::role('technicien')->count();
        $totalAgencies = User::role('agence')->count();

        // Même définition que la file d'approbation (AdminUserController::pendingTechnicians) :
        // un technicien rejeté (désactivé) n'est plus « en attente ».
        $pendingTechnicians = User::role('technicien')
            ->where('verified', false)
            ->where('is_active', true)
            ->count();

        $pendingKyc = KycDocument::where('status', 'pending')->count();
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();

        // Nouveaux utilisateurs ce mois
        $newUsersThisMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Demandes récentes
        $totalRequests = 0;
        $pendingRequests = 0;
        try {
            $totalRequests = ClientRequest::count();
            $pendingRequests = ClientRequest::where('status', 'new')->count();
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        // Derniers utilisateurs inscrits
        $recentUsers = User::with('roles')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->roles->first()?->name ?? 'N/A',
                'is_active' => $u->is_active,
                'created_at' => $u->created_at->toIso8601String(),
            ]);

        return response()->json([
            'total_users' => $totalUsers,
            'total_clients' => $totalClients,
            'total_technicians' => $totalTechnicians,
            'total_agencies' => $totalAgencies,
            'pending_technicians' => $pendingTechnicians,
            'pending_kyc' => $pendingKyc,
            'active_users' => $activeUsers,
            'inactive_users' => $inactiveUsers,
            'new_users_this_month' => $newUsersThisMonth,
            'total_requests' => $totalRequests,
            'pending_requests' => $pendingRequests,
            'recent_users' => $recentUsers,
        ]);
    }
}
