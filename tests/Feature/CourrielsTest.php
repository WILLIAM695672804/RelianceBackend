<?php

namespace Tests\Feature;

use App\Modules\Notification\Services\NotificationService;
use App\Notifications\NotificationParCourriel;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** Courriels envoyés par l'application : français, personnalisés, liens directs. */
class CourrielsTest extends TestCase
{
    use RefreshDatabase, BuildsWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['app.frontend_url' => 'https://app.reliance.example']);
    }

    private function lienDeVerification($user): string
    {
        return (new VerifyEmailNotification())->toMail($user)->actionUrl;
    }

    public function test_le_courriel_de_verification_est_en_francais_et_personnalise(): void
    {
        $user = $this->makeUser('client', ['name' => 'Didier Kamga']);

        $message = (new VerifyEmailNotification())->toMail($user);

        $this->assertSame('Confirmez votre adresse e-mail sur Reliance', $message->subject);
        $this->assertSame('Bonjour Didier,', $message->greeting);
        $this->assertSame('Confirmer mon adresse', $message->actionText);
        $this->assertStringContainsString('Reliance', $message->salutation);
    }

    public function test_le_lien_de_verification_est_direct_sans_adresse_encodee(): void
    {
        $lien = $this->lienDeVerification($this->makeUser('client'));

        $this->assertStringStartsWith('https://app.reliance.example/auth/verify-email?id=', $lien);
        parse_str((string) parse_url($lien, PHP_URL_QUERY), $parametres);
        $this->assertSame(['id', 'hash', 'expires', 'signature'], array_keys($parametres));
        $this->assertStringNotContainsString('url=', $lien);
        $this->assertStringNotContainsString('%2F', $lien);
    }

    public function test_le_lien_reconstitue_par_le_frontend_verifie_l_adresse(): void
    {
        $user = $this->makeUser('client');
        $user->forceFill(['email_verified_at' => null])->save();
        parse_str((string) parse_url($this->lienDeVerification($user), PHP_URL_QUERY), $p);

        // Ce que fait la page /auth/verify-email : chemin de l'API reconstitué à partir des paramètres.
        $this->getJson("/api/v1/auth/email/verify/{$p['id']}/{$p['hash']}?expires={$p['expires']}&signature={$p['signature']}")
            ->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_un_lien_de_verification_altere_est_refuse(): void
    {
        $user = $this->makeUser('client');
        $user->forceFill(['email_verified_at' => null])->save();
        parse_str((string) parse_url($this->lienDeVerification($user), PHP_URL_QUERY), $p);

        $this->getJson("/api/v1/auth/email/verify/{$p['id']}/{$p['hash']}?expires=" . ($p['expires'] + 3600) . "&signature={$p['signature']}")
            ->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_le_courriel_de_reinitialisation_est_en_francais(): void
    {
        Notification::fake();
        $user = $this->makeUser('client', ['name' => 'Estelle Ngo']);

        Password::broker()->sendResetLink(['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $message = $notification->toMail($user);

            return $message->subject === 'Réinitialisation de votre mot de passe Reliance'
                && $message->greeting === 'Bonjour Estelle,'
                && str_starts_with($message->actionUrl, 'https://app.reliance.example/reset-password?token=');
        });
    }

    public function test_une_notification_metier_part_mise_en_forme_avec_un_lien_vers_l_espace(): void
    {
        Notification::fake();
        $tech = $this->makeUser('technicien', ['name' => 'Serge Mbarga']);

        app(NotificationService::class)->notifyProposal($tech->id, 'Fuite sous l\'évier', 'p-1');

        Notification::assertSentTo($tech, NotificationParCourriel::class, function ($notification) use ($tech) {
            $message = $notification->toMail($tech);
            $texte = implode(' ', $message->outroLines);

            return $message->subject === 'Nouvelle proposition de mission — Reliance'
                && $message->greeting === 'Bonjour Serge,'
                && $message->actionUrl === 'https://app.reliance.example/technicien/radar'
                && str_contains($texte, 'https://app.reliance.example/technicien/notifications');
        });
    }

    public function test_le_refus_de_candidature_donne_le_contexte_avant_le_motif(): void
    {
        $tech = $this->makeUser('technicien');

        $message = (new NotificationParCourriel('Candidature technicien refusée', 'Pièce illisible.', ['type' => 'technician_rejected']))
            ->toMail($tech);

        $this->assertStringContainsString('n\'a pas été retenue', $message->introLines[0]);
        $this->assertSame('Pièce illisible.', $message->introLines[1]);
        $this->assertSame('https://app.reliance.example/technicien/verification', $message->actionUrl);
    }

    public function test_un_client_est_dirige_vers_son_propre_espace(): void
    {
        $client = $this->makeUser('client');

        $message = (new NotificationParCourriel('Nouveau devis reçu', 'Un devis vous attend.', ['type' => 'quote_received']))
            ->toMail($client);

        $this->assertSame('https://app.reliance.example/espace-client/suivi', $message->actionUrl);
    }
}
