<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Auth\Models\User;
use Spatie\Permission\Models\Role;

class RoleManagementController extends Controller
{
    /**
     * Assigner un rôle à un utilisateur
     */
    public function assignRole(Request $request, $userId)
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name'
        ]);

        $user = User::findOrFail($userId);
        
        // Empêcher d'assigner le rôle admin si l'action n'est pas fortement protégée
        // (optionnel : logique métier supplémentaire)

        $user->assignRole($request->role);

        return response()->json([
            'message' => 'Rôle assigné avec succès.',
            'roles' => $user->getRoleNames()
        ]);
    }

    /**
     * Révoquer un rôle à un utilisateur
     */
    public function revokeRole(Request $request, $userId, $roleName)
    {
        $user = User::findOrFail($userId);

        if (!$user->hasRole($roleName)) {
            return response()->json([
                'message' => 'L\'utilisateur ne possède pas ce rôle.'
            ], 400);
        }

        // Sécurité : Ne pas permettre de supprimer l'unique Admin
        if ($roleName === 'admin' && User::role('admin')->count() <= 1) {
            return response()->json([
                'message' => 'Impossible de révoquer le dernier administrateur.'
            ], 403);
        }

        $user->removeRole($roleName);

        return response()->json([
            'message' => 'Rôle révoqué avec succès.',
            'roles' => $user->getRoleNames()
        ]);
    }
}
