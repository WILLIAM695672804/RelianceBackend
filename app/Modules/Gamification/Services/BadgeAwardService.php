<?php
namespace App\Modules\Gamification\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Gamification\Models\Badge;
use App\Modules\Gamification\Models\UserBadge;
use App\Modules\Gamification\Models\Review;
use App\Modules\Matching\Models\Proposal;

class BadgeAwardService
{
    public function evaluate(User $user): array
    {
        $awarded = [];
        $existingBadgeCodes = $user->badges()->pluck('code')->toArray();
        $badges = Badge::all();

        foreach ($badges as $badge) {
            if (in_array($badge->code, $existingBadgeCodes)) {
                continue;
            }
            if ($this->meetsCondition($user, $badge->criteria ?? [])) {
                UserBadge::create([
                    'user_id'    => $user->id,
                    'badge_id'   => $badge->id,
                    'awarded_at' => now(),
                ]);
                $awarded[] = $badge->code;
            }
        }
        return $awarded;
    }

    private function meetsCondition(User $user, array $criteria): bool
    {
        $type = $criteria['type'] ?? null;
        return match ($type) {
            'avg_rating' => $this->checkAvgRating($user, $criteria),
            'completed_missions' => $this->checkCompletedMissions($user, $criteria),
            'skill_reviews' => $this->checkSkillReviews($user, $criteria),
            default => false,
        };
    }

    private function checkAvgRating(User $user, array $c): bool
    {
        return (float) $user->rating >= ($c['min_rating'] ?? 5)
            && (int) $user->num_reviews >= ($c['min_reviews'] ?? 1);
    }

    private function checkCompletedMissions(User $user, array $c): bool
    {
        $completedCount = Proposal::where('technician_id', $user->id)
            ->where('status', 'accepted')
            ->count();
        return $completedCount >= ($c['min_count'] ?? 999);
    }

    private function checkSkillReviews(User $user, array $c): bool
    {
        $count = Review::where('target_id', $user->id)
            ->where('rating', '>=', $c['min_rating'] ?? 5)
            ->whereHas('request', function ($q) use ($c) {
                $q->whereHas('category', function ($sq) use ($c) {
                    $sq->where('slug', $c['skill_slug'] ?? '');
                });
            })
            ->count();
        return $count >= ($c['min_count'] ?? 999);
    }
}
