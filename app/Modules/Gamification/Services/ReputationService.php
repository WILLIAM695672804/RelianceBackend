<?php
namespace App\Modules\Gamification\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Gamification\Models\Review;
use App\Modules\Gamification\Models\ReputationChange;

class ReputationService
{
    public function recalculateAfterReview(Review $review): void
    {
        $target = $review->target;

        $stats = Review::where('target_id', $target->id)
            ->where('stage', 'post') // la réputation ne repose que sur les prestations réalisées
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating')
            ->first();

        $target->rating = round($stats->avg_rating, 2);
        $target->num_reviews = $stats->total;
        $target->save();

        ReputationChange::create([
            'user_id' => $target->id,
            'change'  => $review->rating >= 4 ? +10 : ($review->rating <= 2 ? -5 : +2),
            'reason'  => "Review #{$review->id}: {$review->rating} etoiles",
        ]);
    }
}
