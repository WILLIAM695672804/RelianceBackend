<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Auth\Models\User;
use App\Modules\Profile\Models\Skill;
use App\Modules\Profile\Models\Location;
use App\Modules\Schedule\Models\Schedule;
use App\Modules\Schedule\Models\TimeSlot;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestTechnicianSeeder extends Seeder
{
    public function run(): void
    {
        // ========== TECHNICIEN 1 : Electricien a Yaounde ==========
        $tech1 = User::firstOrCreate(
            ['email' => 'tech.electricien@reliance.cm'],
            [
                'name' => 'Jean-Marc Tchoupo',
                'phone' => '+237 699 111 222',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'verified' => true,
                'email_verified_at' => now(),
                'rating' => 4.8,
                'num_reviews' => 32,
                'meta' => [
                    'bio' => 'Electricien certifie avec 8 ans d\'experience. Specialise en installations electriques residentielles et commerciales.',
                    'competences_intro' => ['electricite', 'domotique', 'securite'],
                ],
            ]
        );
        $tech1->assignRole('technicien');
        $this->setupTechnicianData($tech1, 3.8480, 11.5021, [ // Yaounde centre
            'electricite-generale',
            'installation-electrique',
            'depannage-electrique',
            'tableau-electrique',
            'eclairage',
        ]);

        // ========== TECHNICIEN 2 : Plombier a Yaounde ==========
        $tech2 = User::firstOrCreate(
            ['email' => 'tech.plombier@reliance.cm'],
            [
                'name' => 'Samuel Ondoa',
                'phone' => '+237 699 333 444',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'verified' => true,
                'email_verified_at' => now(),
                'rating' => 4.6,
                'num_reviews' => 18,
                'meta' => [
                    'bio' => 'Plombier professionnel. Interventions rapides pour fuites, debouchage et installations sanitaires.',
                    'competences_intro' => ['plomberie', 'sanitaire'],
                ],
            ]
        );
        $tech2->assignRole('technicien');
        $this->setupTechnicianData($tech2, 3.8600, 11.5200, [ // Yaounde nord
            'plomberie-generale',
            'debouchage-canalisation',
            'installation-sanitaire',
            'reparation-fuite',
            'chauffe-eau',
        ]);

        // ========== TECHNICIEN 3 : Climatisation a Yaounde ==========
        $tech3 = User::firstOrCreate(
            ['email' => 'tech.clim@reliance.cm'],
            [
                'name' => 'Fiona Kamga',
                'phone' => '+237 699 555 666',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'verified' => true,
                'email_verified_at' => now(),
                'rating' => 4.9,
                'num_reviews' => 45,
                'meta' => [
                    'bio' => 'Specialiste climatisation et refrigeration. Installation, maintenance et depannage de tous types de systemes.',
                    'competences_intro' => ['climatisation', 'froid'],
                ],
            ]
        );
        $tech3->assignRole('technicien');
        $this->setupTechnicianData($tech3, 3.8400, 11.4900, [ // Yaounde ouest
            'climatisation',
            'refrigeration',
            'ventilation',
        ]);

        // ========== TECHNICIEN 4 : Informatique a Yaounde ==========
        $tech4 = User::firstOrCreate(
            ['email' => 'tech.info@reliance.cm'],
            [
                'name' => 'Patrick Nguesso',
                'phone' => '+237 699 777 888',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'verified' => true,
                'email_verified_at' => now(),
                'rating' => 4.5,
                'num_reviews' => 12,
                'meta' => [
                    'bio' => 'Technicien informatique et reseau. Configuration Wi-Fi, maintenance PC, reparation smartphones.',
                    'competences_intro' => ['informatique', 'reseau'],
                ],
            ]
        );
        $tech4->assignRole('technicien');
        $this->setupTechnicianData($tech4, 3.8550, 11.5100, [ // Yaounde centre-est
            'maintenance-informatique',
            'reseau-wifi',
            'reparation-smartphone',
            'camera-surveillance',
        ]);

        // ========== TECHNICIEN 5 : Multi-competences a Douala ==========
        $tech5 = User::firstOrCreate(
            ['email' => 'tech.multi@reliance.cm'],
            [
                'name' => 'Alain Mbarga',
                'phone' => '+237 699 999 000',
                'password' => Hash::make('password123'),
                'is_active' => true,
                'verified' => true,
                'email_verified_at' => now(),
                'rating' => 4.7,
                'num_reviews' => 55,
                'meta' => [
                    'bio' => 'Technicien polyvalent avec 15 ans d\'experience. Electricite, plomberie, peinture, carrelage.',
                    'competences_intro' => ['batiment', 'electricite', 'plomberie'],
                ],
            ]
        );
        $tech5->assignRole('technicien');
        $this->setupTechnicianData($tech5, 4.0511, 9.7679, [ // Douala
            'electricite-generale',
            'plomberie-generale',
            'peinture',
            'carrelage',
            'soudure',
        ]);

        $this->command->info('5 techniciens de test crees avec locations, skills et schedules.');
    }

    private function setupTechnicianData(User $user, float $lat, float $lng, array $skillSlugs): void
    {
        // 1. Create default location with PostGIS geom
        $existingLoc = Location::where('user_id', $user->id)->where('is_default', true)->first();
        if (!$existingLoc) {
            $locId = (string) Str::uuid();
            DB::table('locations')->insert([
                'id' => $locId,
                'user_id' => $user->id,
                'label' => 'Zone d\'intervention principale',
                'city' => $lat > 4.0 ? 'Douala' : 'Yaounde',
                'address' => $lat > 4.0 ? 'Douala, Cameroun' : 'Yaounde, Cameroun',
                'is_default' => true,
                'meta' => json_encode([
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'radius' => 50,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Set PostGIS geom
            DB::statement(
                "UPDATE locations SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?",
                [$lng, $lat, $locId]
            );
        }

        // 2. Attach skills
        foreach ($skillSlugs as $slug) {
            $skill = Skill::where('slug', $slug)->first();
            if ($skill && !$user->skills()->where('skill_id', $skill->id)->exists()) {
                $user->skills()->attach($skill->id, [
                    'id' => (string) Str::uuid(),
                    'proficiency' => rand(3, 5),
                    'hourly_rate' => rand(3, 15) * 1000,
                    'travel_fee' => rand(1, 5) * 500,
                ]);
            }
        }

        // 3. Create schedule with time slots (all week, 7h-20h)
        $schedule = $user->schedule;
        if (!$schedule) {
            $schedule = Schedule::create([
                'user_id' => $user->id,
                'tz' => 'Africa/Douala',
            ]);

            // Monday to Saturday (1-6), plus Sunday (0) half-day
            for ($day = 0; $day <= 6; $day++) {
                TimeSlot::create([
                    'schedule_id' => $schedule->id,
                    'day_of_week' => $day,
                    'start_time' => $day === 0 ? '09:00:00' : '07:00:00',
                    'end_time' => $day === 0 ? '14:00:00' : '20:00:00',
                    'repeat_weekly' => true,
                ]);
            }
        }
    }
}
