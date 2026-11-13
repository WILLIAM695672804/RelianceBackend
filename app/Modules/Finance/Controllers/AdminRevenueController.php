<?php

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\PlatformRevenue;
use Illuminate\Support\Facades\DB;

class AdminRevenueController extends Controller
{
    /** GET /v1/admin/revenue — Revenus de la plateforme : total, par source, par mois (6 derniers mois). */
    public function index()
    {
        $parSource = PlatformRevenue::select('source', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as operations'))
            ->groupBy('source')->orderByDesc('total')->get();

        $parMois = PlatformRevenue::select(DB::raw("to_char(created_at, 'YYYY-MM') as mois"), DB::raw('SUM(amount) as total'))
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('mois')->orderBy('mois')->get();

        return response()->json(['data' => [
            'currency' => 'XAF',
            'total' => (float) PlatformRevenue::sum('amount'),
            'by_source' => $parSource,
            'by_month' => $parMois,
        ]]);
    }
}
