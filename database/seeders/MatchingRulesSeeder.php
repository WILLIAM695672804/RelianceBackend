<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Matching\Models\MatchingRule;

class MatchingRulesSeeder extends Seeder
{
    public function run(): void
    {
        MatchingRule::firstOrCreate(
            ['name' => 'default_scoring_weights'],
            [
                'priority' => 1,
                'enabled'  => true,
                'rule_json' => [
                    'weights' => [
                        'distance'     => 0.30,
                        'rating'       => 0.25,
                        'proficiency'  => 0.20,
                        'availability' => 0.15,
                        'success_rate' => 0.10,
                    ],
                    'max_results'                    => 5,
                    'max_radius_km'                  => 50,
                    'default_proposal_expiry_hours'  => 24,
                ],
            ]
        );
    }
}
