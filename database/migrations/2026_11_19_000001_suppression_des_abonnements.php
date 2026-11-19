<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le système ne gère pas d'abonnement : suppression des tables `subscriptions` et
 * `subscription_plans`, et de la colonne `has_subscription` de la vue des techniciens disponibles.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Une vue ne perd pas de colonne par CREATE OR REPLACE : elle est recréée.
        DB::statement('DROP VIEW IF EXISTS vw_available_technicians');
        DB::statement(self::availableTechniciansView(withSubscription: false));

        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }

    public function down(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 10, 2);
            $table->decimal('price_yearly', 10, 2);
            $table->string('currency')->default('XAF');
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subscriber_id');
            $table->string('plan_name');
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('recurring')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('subscriber_id')->references('id')->on('users')->cascadeOnDelete();
        });

        DB::statement('DROP VIEW IF EXISTS vw_available_technicians');
        DB::statement(self::availableTechniciansView(withSubscription: true));
    }

    private static function availableTechniciansView(bool $withSubscription): string
    {
        $subscriptionColumn = $withSubscription ? ",\n                COALESCE(sub.active, false) AS has_subscription" : '';
        $subscriptionJoin = $withSubscription ? "
            LEFT JOIN (
                SELECT subscriber_id, true AS active FROM subscriptions WHERE status = 'active'
            ) sub ON sub.subscriber_id = u.id" : '';

        return "
            CREATE VIEW vw_available_technicians AS
            SELECT
                u.id AS technician_id,
                u.name AS display_name,
                u.rating,
                ts.skill_id,
                s.name AS skill_name,
                ts.hourly_rate,
                ts.travel_fee,
                l.geom AS location_geom,
                ST_X(l.geom::geometry) AS lon,
                ST_Y(l.geom::geometry) AS lat{$subscriptionColumn}
            FROM technician_skills ts
            JOIN users u ON u.id = ts.user_id AND u.is_active = true
            JOIN skills s ON s.id = ts.skill_id
            LEFT JOIN LATERAL (
                SELECT l2.* FROM locations l2 WHERE l2.user_id = u.id AND l2.is_default = true LIMIT 1
            ) l ON true{$subscriptionJoin}
            WHERE l.geom IS NOT NULL
        ";
    }
};
