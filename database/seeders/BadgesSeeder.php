<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Gamification\Models\Badge;

class BadgesSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'code'        => 'expert_plumber',
                'title'       => 'Expert Plombier',
                'description' => 'A recu 10 avis 5 etoiles en plomberie',
                'icon_ref'    => 'plumbing',
                'criteria'    => ['type' => 'skill_reviews', 'skill_slug' => 'plomberie', 'min_rating' => 5, 'min_count' => 10],
            ],
            [
                'code'        => 'top_performer',
                'title'       => 'Top Performer',
                'description' => 'Note moyenne superieure a 4.5 avec au moins 20 avis',
                'icon_ref'    => 'emoji_events',
                'criteria'    => ['type' => 'avg_rating', 'min_rating' => 4.5, 'min_reviews' => 20],
            ],
            [
                'code'        => 'reliable',
                'title'       => 'Fiable',
                'description' => '50 missions completees sans annulation',
                'icon_ref'    => 'verified_user',
                'criteria'    => ['type' => 'completed_missions', 'min_count' => 50, 'max_cancellation_rate' => 0],
            ],
            [
                'code'        => 'fast_responder',
                'title'       => 'Reactif',
                'description' => 'Accepte les propositions en moins de 30 minutes en moyenne',
                'icon_ref'    => 'speed',
                'criteria'    => ['type' => 'response_time', 'max_avg_minutes' => 30],
            ],
            [
                'code'        => 'fifty_missions',
                'title'       => '50 Missions',
                'description' => 'A complete 50 missions avec succes',
                'icon_ref'    => 'military_tech',
                'criteria'    => ['type' => 'completed_missions', 'min_count' => 50],
            ],
            [
                'code'        => 'hundred_missions',
                'title'       => '100 Missions',
                'description' => 'A complete 100 missions avec succes',
                'icon_ref'    => 'workspace_premium',
                'criteria'    => ['type' => 'completed_missions', 'min_count' => 100],
            ],
        ];

        foreach ($badges as $badge) {
            Badge::firstOrCreate(['code' => $badge['code']], $badge);
        }
    }
}
