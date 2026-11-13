<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Le lien de réinitialisation pointe vers le FRONTEND (SPA), pas vers l'API.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            // config() et non env() : env() renvoie null hors des fichiers de configuration une fois celle-ci en cache.
            $base = rtrim((string) config('app.frontend_url'), '/');
            return $base . '/reset-password?token=' . $token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
