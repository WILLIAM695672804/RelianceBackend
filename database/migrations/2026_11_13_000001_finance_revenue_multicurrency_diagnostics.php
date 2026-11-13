<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Journal des revenus de la plateforme (commissions, abonnements) : un mouvement = une ligne.
        Schema::create('platform_revenues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id')->nullable();
            $table->string('source', 50); // job_commission, diagnostic_commission, subscription…
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('XAF');
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->index(['source', 'created_at']);
        });

        // Portefeuilles multi-devises : un portefeuille par (utilisateur, devise).
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->unique(['user_id', 'currency']);
        });

        // Un seul diagnostic par demande.
        Schema::table('diagnostics', function (Blueprint $table) {
            $table->unique('request_id');
        });

        // Règles SLA par défaut, déclenchées automatiquement (retard à l'arrivée, annulation par le technicien).
        $now = now();
        foreach ([
            ['late_arrival', 'Retard à l\'arrivée', 'Le technicien arrive plus de 15 minutes après l\'horaire convenu.', 3],
            ['technician_cancellation', 'Annulation par le technicien', 'Le technicien annule une mission qu\'il avait acceptée.', 10],
        ] as [$key, $name, $description, $points]) {
            if (!DB::table('sla_rules')->where('event_key', $key)->exists()) {
                DB::table('sla_rules')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'name' => $name, 'description' => $description, 'event_key' => $key,
                    'penalty_type' => 'reputation', 'penalty_value' => $points, 'active' => true,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('diagnostics', fn (Blueprint $t) => $t->dropUnique(['request_id']));
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'currency']);
            $table->unique('user_id');
        });
        Schema::dropIfExists('platform_revenues');
        DB::table('sla_rules')->whereIn('event_key', ['late_arrival', 'technician_cancellation'])->delete();
    }
};
