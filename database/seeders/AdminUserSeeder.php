<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@reliance.cm'],
            [
                'name' => 'Administrateur',
                'phone' => '+237600000000',
                'password' => Hash::make('Admin@2024!'),
                'is_active' => true,
                'verified' => true,
                'email_verified_at' => now(),
            ]
        );

        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
