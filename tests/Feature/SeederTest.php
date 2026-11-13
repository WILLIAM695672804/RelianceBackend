<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_hors_developpement_ni_admin_par_defaut_ni_comptes_de_demonstration(): void
    {
        $this->app['env'] = 'production';
        config(['reliance.admin.password' => null]);

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, User::count());
    }

    public function test_hors_developpement_le_mot_de_passe_de_developpement_est_refuse(): void
    {
        $this->app['env'] = 'production';
        config(['reliance.admin.password' => 'Admin@2024!']);

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, User::count());
    }

    public function test_hors_developpement_l_admin_est_cree_avec_les_identifiants_fournis(): void
    {
        $this->app['env'] = 'production';
        config(['reliance.admin.email' => 'root@exemple.test', 'reliance.admin.password' => 'Un-mot-de-passe-robuste-42']);

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $admin = User::sole();
        $this->assertSame('root@exemple.test', $admin->email);
        $this->assertTrue(Hash::check('Un-mot-de-passe-robuste-42', $admin->password));
        $this->assertTrue($admin->hasRole('admin'));
    }

    public function test_en_developpement_les_comptes_de_demonstration_sont_crees(): void
    {
        $this->app['env'] = 'local';

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertTrue(User::where('email', 'admin@reliance.cm')->exists());
        $this->assertTrue(User::where('email', 'tech.electricien@reliance.cm')->exists());
    }
}
