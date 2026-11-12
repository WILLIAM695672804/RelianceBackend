<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Modules\Governance\Observers\AuditableObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pusher résilient : voir ResilientPusherBroadcaster.
        \Illuminate\Support\Facades\Broadcast::extend('pusher', function ($app, array $config) {
            $pusher = $app->make(\Illuminate\Broadcasting\BroadcastManager::class)->pusher($config);

            return new \App\Broadcasting\ResilientPusherBroadcaster($pusher);
        });

        $auditableModels = [
            \App\Modules\Auth\Models\User::class,
            \App\Modules\Finance\Models\Wallet::class,
            \App\Modules\Operations\Models\Quote::class,
            \App\Modules\Operations\Models\Invoice::class,
            \App\Modules\Matching\Models\ClientRequest::class,
            \App\Modules\Matching\Models\Proposal::class,
            \App\Modules\Kyc\Models\KycDocument::class,
            \App\Modules\Governance\Models\SlaRule::class,
        ];

        foreach ($auditableModels as $model) {
            $model::observe(AuditableObserver::class);
        }
    }
}
