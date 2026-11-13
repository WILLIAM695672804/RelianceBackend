<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /** Mot de passe de développement uniquement : refusé hors environnement local. */
    private const DEV_PASSWORD = 'Admin@2024!';

    public function run(): void
    {
        $email = config('reliance.admin.email') ?: 'admin@reliance.cm';
        $password = config('reliance.admin.password');

        if (!app()->environment('local', 'testing')) {
            if (!$password || mb_strlen($password) < 12 || $password === self::DEV_PASSWORD) {
                $this->command?->warn('Administrateur non créé : définir ADMIN_PASSWORD (12 caractères minimum).');
                return;
            }
        }

        $admin = User::where('email', $email)->first();
        if (!$admin) {
            $admin = new User();
            // forceFill : is_active, verified et email_verified_at ne sont pas assignables en masse.
            $admin->forceFill([
                'name' => 'Administrateur',
                'email' => $email,
                'phone' => '+237600000000',
                'password' => Hash::make($password ?: self::DEV_PASSWORD),
                'is_active' => true,
                'verified' => true,
                'email_verified_at' => now(),
            ])->save();
        }

        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
