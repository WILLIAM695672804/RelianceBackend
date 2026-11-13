<?php

namespace App\Modules\Profile\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Profile\Requests\UpdateTechProfileRequest;
use App\Modules\Profile\Requests\UpdateTechLocationRequest;
use App\Modules\Profile\Resources\IdentityHubResource;
use App\Modules\Auth\Models\User;
use App\Modules\Profile\Models\Location;
use Illuminate\Support\Facades\DB;

class TechnicianProfileController extends Controller
{
    public function show(Request $request, $id = null)
    {
        $userId = $id ?? $request->user()->id;
        $user = User::with(['skills', 'locations'])->findOrFail($userId);
        
        return response()->json([
            'status' => 'success',
            'data' => new IdentityHubResource($user)
        ]);
    }

    public function updateProfile(UpdateTechProfileRequest $request)
    {
        $user = $request->user();
        
        $validated = $request->validated();
        
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['phone'])) {
            $user->phone = $validated['phone'];
        }

        // Handle Avatar Upload
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            
            // Delete old avatar if exists
            if ($user->avatar_url && !str_starts_with($user->avatar_url, 'http')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_url);
            }
            
            $user->avatar_url = $path; // Or full URL via Storage::url($path)
        }

        // Generate JSON meta for extended fields
        $meta = $user->meta ?? [];
        if (isset($validated['bio'])) {
            $meta['bio'] = $validated['bio'];
        }
        if (isset($validated['competences'])) {
            $meta['competences'] = $validated['competences'];
        }
        $user->meta = $meta;

        $user->save();

        return response()->json([
            'status' => 'success',
            'data' => new IdentityHubResource($user)
        ]);
    }

    public function updateLocation(UpdateTechLocationRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();
        
        $location = Location::firstOrCreate(
            ['user_id' => $user->id, 'is_default' => true],
            ['address' => $validated['address'] ?? null, 'city' => $validated['city'] ?? null]
        );

        $location->address = $validated['address'] ?? $location->address;
        $location->city = $validated['city'] ?? $location->city;
        
        $meta = $location->meta ?? [];
        $meta['zones'] = $validated['zones'] ?? [];
        $meta['latitude'] = $validated['latitude'];
        $meta['longitude'] = $validated['longitude'];
        $meta['radius'] = $validated['radius'] ?? 50;
        $location->meta = $meta;
        
        $location->save();

        // Spatial Query: UPDATE PostGIS point
        DB::statement(
            "UPDATE locations SET geom = ST_GeomFromText(?, 4326) WHERE id = ?",
            ["POINT({$validated['longitude']} {$validated['latitude']})", $location->id]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Location and perimeter updated successfully',
            'data' => new IdentityHubResource($user)
        ]);
    }

    /**
     * GET /technician/stats — KPIs du dashboard technicien (gains, missions, note).
     * Tout est calculé à partir des vraies données (wallet, transactions, jobs).
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        $userId = $user->id;

        // ── Wallet & gains (crédits) ────────────────────────────
        $wallet = \App\Modules\Finance\Models\Wallet::xaf()->where('user_id', $userId)->first();
        $balance = $wallet ? (float) $wallet->balance : 0.0;
        $available = $wallet ? (float) $wallet->availableBalance() : 0.0;

        $totalEarnings = 0.0;
        $monthlyEarnings = 0.0;
        if ($wallet) {
            $base = \App\Modules\Finance\Models\Transaction::where('wallet_id', $wallet->id)->where('type', 'credit');
            $totalEarnings = (float) (clone $base)->sum('amount');
            $monthlyEarnings = (float) (clone $base)->where('created_at', '>=', now()->startOfMonth())->sum('amount');
        }

        // ── Missions ────────────────────────────────────────────
        $jobs = \App\Modules\Operations\Models\TechnicianJob::where('technician_id', $userId);
        $missionsTotal = (clone $jobs)->count();
        $missionsCompleted = (clone $jobs)->where('status', 'completed')->count();
        $completionRate = $missionsTotal > 0 ? round($missionsCompleted / $missionsTotal * 100, 1) : 0;

        // Répartition des missions par catégorie
        $categoryBreakdown = \App\Modules\Operations\Models\TechnicianJob::where('technician_id', $userId)
            ->with('request.category')
            ->get()
            ->groupBy(fn ($j) => optional(optional($j->request)->category)->name ?? 'Autre')
            ->map(fn ($g, $k) => ['label' => $k, 'value' => $g->count()])
            ->values();

        // Missions par mois (6 derniers mois)
        $monthlyMissions = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $end = (clone $start)->endOfMonth();
            $monthlyMissions[] = [
                'label' => $start->format('M'),
                'count' => (clone $jobs)->whereBetween('created_at', [$start, $end])->count(),
            ];
        }

        // Missions récentes
        $recentMissions = \App\Modules\Operations\Models\TechnicianJob::where('technician_id', $userId)
            ->with(['request.category', 'request.address'])
            ->orderByDesc('created_at')->limit(6)->get()
            ->map(fn ($j) => [
                'id' => $j->id,
                'title' => optional($j->request)->title ?? 'Mission',
                'category' => optional(optional($j->request)->category)->name ?? '—',
                'city' => optional(optional($j->request)->address)->city ?? '—',
                'status' => $j->status,
            ]);

        return response()->json([
            'wallet_balance' => $balance,
            'available_balance' => $available,
            'total_earnings' => $totalEarnings,
            'monthly_earnings' => $monthlyEarnings,
            'rating' => (float) $user->rating,
            'num_reviews' => (int) $user->num_reviews,
            'missions_total' => $missionsTotal,
            'missions_completed' => $missionsCompleted,
            'completion_rate' => $completionRate,
            'category_breakdown' => $categoryBreakdown,
            'monthly_missions' => $monthlyMissions,
            'recent_missions' => $recentMissions,
        ]);
    }
}
