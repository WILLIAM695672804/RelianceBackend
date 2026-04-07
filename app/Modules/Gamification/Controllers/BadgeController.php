<?php
namespace App\Modules\Gamification\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Gamification\Models\Badge;
use App\Modules\Gamification\Models\UserBadge;
use App\Modules\Gamification\Models\ReputationChange;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    public function index()
    {
        return response()->json(['data' => Badge::all()]);
    }

    public function userBadges(string $userId)
    {
        $badges = UserBadge::where('user_id', $userId)
            ->with('badge')
            ->orderBy('awarded_at', 'desc')
            ->get()
            ->map(fn($ub) => [
                'code'       => $ub->badge->code,
                'title'      => $ub->badge->title,
                'icon_ref'   => $ub->badge->icon_ref,
                'awarded_at' => $ub->awarded_at,
            ]);

        return response()->json(['data' => $badges]);
    }

    public function reputation(string $userId)
    {
        $changes = ReputationChange::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $totalReputation = ReputationChange::where('user_id', $userId)->sum('change');

        return response()->json([
            'data'  => $changes,
            'total' => $totalReputation,
        ]);
    }
}
