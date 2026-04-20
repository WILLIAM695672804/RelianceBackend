<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Subscription;
use App\Modules\Finance\Models\SubscriptionPlan;
use App\Modules\Finance\Resources\SubscriptionResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    /**
     * GET /v1/subscriptions/plans — Available subscription plans.
     */
    public function plans()
    {
        $plans = SubscriptionPlan::where('active', true)->get();
        return response()->json(['data' => $plans]);
    }

    /**
     * GET /v1/subscriptions/mine — Current user's active subscription.
     */
    public function mine(Request $request)
    {
        $subscription = Subscription::where('subscriber_id', $request->user()->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json(['data' => null, 'message' => 'Aucun abonnement actif']);
        }

        return response()->json(['data' => new SubscriptionResource($subscription)]);
    }

    /**
     * POST /v1/subscriptions/subscribe — Subscribe to a plan.
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $user = $request->user();

        // Check if already has active subscription
        $existing = Subscription::where('subscriber_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Vous avez déjà un abonnement actif. Annulez-le d\'abord.'], 422);
        }

        $startDate = Carbon::now();
        $endDate = $request->billing_cycle === 'monthly'
            ? $startDate->copy()->addMonth()
            : $startDate->copy()->addYear();

        $price = $request->billing_cycle === 'monthly'
            ? $plan->price_monthly
            : $plan->price_yearly;

        $subscription = Subscription::create([
            'subscriber_id' => $user->id,
            'plan_name' => $plan->slug,
            'status' => 'active',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'recurring' => true,
            'metadata' => [
                'plan_id' => $plan->id,
                'billing_cycle' => $request->billing_cycle,
                'price' => $price,
                'currency' => $plan->currency,
            ],
        ]);

        return response()->json([
            'message' => 'Abonnement souscrit avec succès',
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    /**
     * POST /v1/subscriptions/cancel — Cancel current subscription.
     */
    public function cancel(Request $request)
    {
        $subscription = Subscription::where('subscriber_id', $request->user()->id)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $subscription->update([
            'status' => 'cancelled',
            'recurring' => false,
        ]);

        return response()->json([
            'message' => 'Abonnement annulé. Il reste actif jusqu\'au ' . $subscription->end_date->format('d/m/Y'),
        ]);
    }

    // ── Admin endpoints ──────────────────────────────────────

    /**
     * GET /v1/admin/subscriptions — All subscriptions.
     */
    public function adminIndex(Request $request)
    {
        $subscriptions = Subscription::with('subscriber')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return SubscriptionResource::collection($subscriptions);
    }

    /**
     * GET /v1/admin/subscriptions/stats — Subscription statistics.
     */
    public function adminStats()
    {
        $active = Subscription::where('status', 'active')->count();
        $cancelled = Subscription::where('status', 'cancelled')->count();
        $trial = Subscription::where('status', 'trial')->count();
        $total = Subscription::count();

        // Revenue estimate based on metadata
        $monthlyRevenue = Subscription::where('status', 'active')
            ->get()
            ->sum(function ($sub) {
                $meta = $sub->metadata ?? [];
                return ($meta['billing_cycle'] ?? 'monthly') === 'monthly'
                    ? ($meta['price'] ?? 0)
                    : (($meta['price'] ?? 0) / 12);
            });

        return response()->json([
            'active' => $active,
            'cancelled' => $cancelled,
            'trial' => $trial,
            'total' => $total,
            'estimated_monthly_revenue' => round($monthlyRevenue, 2),
        ]);
    }

    /**
     * PUT /v1/admin/subscriptions/{id} — Admin update subscription.
     */
    public function adminUpdate(Request $request, string $id)
    {
        $subscription = Subscription::findOrFail($id);

        $request->validate([
            'status' => 'sometimes|in:active,cancelled,trial,expired',
            'end_date' => 'sometimes|date',
        ]);

        $subscription->update($request->only(['status', 'end_date']));

        return response()->json([
            'message' => 'Abonnement mis à jour',
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    /**
     * POST /v1/admin/subscriptions/plans — Create a new plan.
     */
    public function createPlan(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|unique:subscription_plans,slug',
            'description' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'currency' => 'sometimes|string|max:3',
            'features' => 'nullable|array',
        ]);

        $plan = SubscriptionPlan::create($request->all());

        return response()->json([
            'message' => 'Plan créé',
            'data' => $plan,
        ], 201);
    }

    /**
     * PUT /v1/admin/subscriptions/plans/{id} — Update an existing plan.
     */
    public function updatePlan(Request $request, string $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'price_monthly' => 'sometimes|numeric|min:0',
            'price_yearly' => 'sometimes|numeric|min:0',
            'features' => 'nullable|array',
            'active' => 'sometimes|boolean',
        ]);

        $plan->update($request->all());

        return response()->json([
            'message' => 'Plan mis à jour',
            'data' => $plan,
        ]);
    }
}
