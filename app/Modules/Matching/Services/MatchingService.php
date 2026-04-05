<?php

namespace App\Modules\Matching\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Models\MatchingLog;
use App\Modules\Matching\Models\MatchingRule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MatchingService
{
    private array $weights;
    private int $maxResults;
    private float $maxRadiusKm;

    public function __construct()
    {
        // Load config from the plan's matching_rules schema: name / enabled / rule_json
        $rule = MatchingRule::where('name', 'default_scoring_weights')
            ->where('enabled', true)
            ->first();

        $config = $rule ? ($rule->rule_json ?? []) : [];
        $w = $config['weights'] ?? [];

        $this->weights = [
            'distance'     => (float) ($w['distance']     ?? 0.30),
            'rating'       => (float) ($w['rating']       ?? 0.25),
            'proficiency'  => (float) ($w['proficiency']  ?? 0.20),
            'availability' => (float) ($w['availability'] ?? 0.15),
            'success_rate' => (float) ($w['success_rate'] ?? 0.10),
        ];
        $this->maxResults  = (int)   ($config['max_results']   ?? 5);
        $this->maxRadiusKm = (float) ($config['max_radius_km'] ?? 50);
    }

    /**
     * Find and rank the best technicians for a client request.
     *
     * @return array{technicians: array, scoring_details: array}
     */
    public function match(ClientRequest $clientRequest): array
    {
        $address = $clientRequest->address;
        if (!$address) {
            return ['technicians' => [], 'scoring_details' => ['error' => 'No address on request']];
        }

        $locMeta   = $address->meta ?? [];
        $clientLat = $locMeta['latitude']  ?? null;
        $clientLng = $locMeta['longitude'] ?? null;

        if (!$clientLat || !$clientLng) {
            return ['technicians' => [], 'scoring_details' => ['error' => 'No coordinates on address']];
        }

        $radiusMeters = $this->maxRadiusKm * 1000;
        $skillId      = $clientRequest->detected_category;

        // Step 1: Find technicians within radius, with required skill, verified and active
        $candidates = $this->findCandidates($clientLng, $clientLat, $radiusMeters, $skillId);

        if ($candidates->isEmpty()) {
            $this->logMatching($clientRequest, [], []);
            return ['technicians' => [], 'scoring_details' => ['reason' => 'No candidates in range']];
        }

        // Step 2: Score each candidate
        $scored = [];
        foreach ($candidates as $tech) {
            $scores   = $this->scoreTechnician($tech, $clientLat, $clientLng, $skillId);
            $scored[] = [
                'technician' => $tech,
                'scores'     => $scores,
                'total'      => $scores['total'],
            ];
        }

        // Step 3: Sort by total score descending, take top N
        usort($scored, fn($a, $b) => $b['total'] <=> $a['total']);
        $topN = array_slice($scored, 0, $this->maxResults);

        // Step 4: Log the matching
        $this->logMatching($clientRequest, $topN, $this->weights);

        return [
            'technicians' => array_map(fn($s) => [
                'id'          => $s['technician']->id,
                'name'        => $s['technician']->name,
                'avatar_url'  => $s['technician']->avatar_url,
                'rating'      => (float) $s['technician']->rating,
                'num_reviews' => (int) $s['technician']->num_reviews,
                'verified'    => (bool) $s['technician']->verified,
                'distance_km' => round($s['technician']->distance_km, 1),
                'match_score' => round($s['total'] * 100),
                'scores'      => $s['scores'],
                'skills'      => $s['technician']->skills->map(fn($sk) => [
                    'name'        => $sk->name,
                    'proficiency' => $sk->pivot->proficiency,
                    'hourly_rate' => $sk->pivot->hourly_rate,
                ]),
            ], $topN),
            'scoring_details' => [
                'weights'          => $this->weights,
                'candidates_found' => $candidates->count(),
                'returned'         => count($topN),
                'radius_km'        => $this->maxRadiusKm,
            ],
        ];
    }

    private function findCandidates(float $lng, float $lat, float $radiusMeters, ?string $skillId)
    {
        $query = User::role('technicien')
            ->where('verified', true)
            ->where('is_active', true)
            ->with('skills')
            ->whereHas('locations', function ($q) use ($lng, $lat, $radiusMeters) {
                $q->where('is_default', true)
                  ->whereRaw(
                      'ST_DWithin(geom, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)',
                      [$lng, $lat, $radiusMeters]
                  );
            })
            ->addSelect([
                'users.*',
                DB::raw("(
                    SELECT ST_Distance(
                        l.geom,
                        ST_SetSRID(ST_MakePoint({$lng}, {$lat}), 4326)::geography
                    ) / 1000
                    FROM locations l
                    WHERE l.user_id = users.id AND l.is_default = true
                    LIMIT 1
                ) as distance_km"),
            ]);

        if ($skillId) {
            $query->whereHas('skills', fn($q) => $q->where('skills.id', $skillId));
        }

        return $query->get();
    }

    private function scoreTechnician($tech, float $clientLat, float $clientLng, ?string $skillId): array
    {
        // 1. Distance score (closer = higher, normalized 0-1)
        $distanceKm    = $tech->distance_km ?? $this->maxRadiusKm;
        $distanceScore = max(0, 1 - ($distanceKm / $this->maxRadiusKm));

        // 2. Rating score (0-5 → 0-1)
        $ratingScore = min(1, (float) $tech->rating / 5);

        // 3. Proficiency score for the requested skill
        $proficiencyScore = 0;
        if ($skillId) {
            $matchedSkill = $tech->skills->firstWhere('id', $skillId);
            if ($matchedSkill) {
                $proficiencyScore = min(1, $matchedSkill->pivot->proficiency / 5);
            }
        }

        // 4. Availability score (has schedule with slots for today's day)
        $availabilityScore = $this->checkAvailability($tech) ? 1.0 : 0.3;

        // 5. Success rate (based on num_reviews as proxy)
        $successRateScore = min(1, (int) $tech->num_reviews / 50);

        // Weighted total
        $total = ($distanceScore    * $this->weights['distance'])
               + ($ratingScore      * $this->weights['rating'])
               + ($proficiencyScore * $this->weights['proficiency'])
               + ($availabilityScore * $this->weights['availability'])
               + ($successRateScore  * $this->weights['success_rate']);

        return [
            'distance'     => round($distanceScore, 3),
            'rating'       => round($ratingScore, 3),
            'proficiency'  => round($proficiencyScore, 3),
            'availability' => round($availabilityScore, 3),
            'success_rate' => round($successRateScore, 3),
            'total'        => round($total, 4),
        ];
    }

    private function checkAvailability(User $tech): bool
    {
        $schedule = $tech->schedule;
        if (!$schedule) {
            return false;
        }

        $now        = Carbon::now($schedule->tz);
        $dayOfWeek  = $now->dayOfWeek;
        $currentTime = $now->format('H:i:s');

        $hasSlot = $schedule->timeSlots()
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>=', $currentTime)
            ->exists();

        if (!$hasSlot) {
            return false;
        }

        $hasException = $schedule->exceptions()
            ->where('start_ts', '<=', $now)
            ->where('end_ts', '>=', $now)
            ->exists();

        return !$hasException;
    }

    private function logMatching(ClientRequest $request, array $scored, array $weights): void
    {
        MatchingLog::create([
            'request_id'           => $request->id,
            'chosen_technician_id' => !empty($scored) ? $scored[0]['technician']->id : null,
            'candidates'           => array_map(fn($s) => [
                'id'    => $s['technician']->id,
                'score' => $s['total'],
            ], $scored),
            'scoring_details' => [
                'weights'   => $weights,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
