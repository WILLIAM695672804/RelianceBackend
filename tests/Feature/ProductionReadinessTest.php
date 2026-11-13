<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use App\Modules\Kyc\Models\KycDocument;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('public');
    }

    private function configurerProduction(array $surcharges = []): void
    {
        $this->app['env'] = 'production';
        config(array_merge([
            'app.debug' => false,
            'app.url' => 'https://api.reliance.example',
            'app.frontend_url' => 'https://app.reliance.example',
            'logging.default' => 'stack',
            'logging.channels.stack.channels' => ['single'],
            'logging.channels.single.level' => 'warning',
            'session.secure' => true,
            'sanctum.stateful' => ['app.reliance.example'],
            'services.notchpay.private_key' => 'sk_live',
            'services.notchpay.hash_key' => 'hk_live',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.reliance.example',
        ], $surcharges));
    }

    public function test_une_configuration_de_production_complete_est_acceptee(): void
    {
        $this->configurerProduction();

        $this->artisan('reliance:verifier-production')->assertSuccessful();
    }

    public function test_le_mode_debogage_bloque_le_deploiement(): void
    {
        $this->configurerProduction(['app.debug' => true]);

        $this->artisan('reliance:verifier-production')
            ->expectsOutputToContain('APP_DEBUG')
            ->assertFailed();
    }

    public function test_la_configuration_de_developpement_est_refusee(): void
    {
        $this->configurerProduction([
            'app.url' => 'http://192.168.1.159:8010',
            'logging.channels.single.level' => 'debug',
            'sanctum.stateful' => ['127.0.0.1:5173'],
            'services.notchpay.hash_key' => null,
            'mail.mailers.smtp.host' => 'sandbox.smtp.mailtrap.io',
        ]);

        $this->artisan('reliance:verifier-production')
            ->expectsOutputToContain('APP_URL')
            ->expectsOutputToContain('LOG_LEVEL')
            ->expectsOutputToContain('SANCTUM_STATEFUL_DOMAINS')
            ->expectsOutputToContain('NOTCHPAY_HASH_KEY')
            ->expectsOutputToContain('MAIL_HOST')
            ->assertFailed();
    }

    public function test_une_piece_kyc_sur_le_disque_public_bloque_le_deploiement(): void
    {
        $this->configurerProduction();
        Storage::disk('public')->put('kyc/expose.jpg', 'x');
        KycDocument::create([
            'user_id' => $this->makeUser('technicien')->id, 'type' => 'cni',
            'file_path' => 'kyc/expose.jpg', 'status' => 'pending',
        ]);

        $this->artisan('reliance:verifier-production')
            ->expectsOutputToContain('kyc:privatiser-stockage')
            ->assertFailed();
    }

    public function test_le_lien_de_reinitialisation_suit_la_configuration_et_non_l_environnement(): void
    {
        config(['app.frontend_url' => 'https://app.reliance.example']);
        $user = $this->makeUser('client');

        $url = (new ResetPassword('jeton'))->toMail($user)->actionUrl;

        $this->assertStringStartsWith('https://app.reliance.example/reset-password?token=jeton', $url);
    }

    public function test_les_mandataires_declares_transmettent_l_ip_du_client(): void
    {
        $requete = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);
        $ip = fn () => $this->app->make(TrustProxies::class)->handle(clone $requete, fn (Request $r) => $r->ip());

        config(['reliance.trusted_proxies' => null]);
        $this->assertSame('10.0.0.5', $ip());

        config(['reliance.trusted_proxies' => '10.0.0.0/8']);
        $this->assertSame('203.0.113.9', $ip());
    }
}
