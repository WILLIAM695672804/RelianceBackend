<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW vw_available_technicians AS
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
                ST_Y(l.geom::geometry) AS lat,
                COALESCE(sub.active, false) AS has_subscription
            FROM technician_skills ts
            JOIN users u ON u.id = ts.user_id AND u.is_active = true
            JOIN skills s ON s.id = ts.skill_id
            LEFT JOIN LATERAL (
                SELECT l2.* FROM locations l2 WHERE l2.user_id = u.id AND l2.is_default = true LIMIT 1
            ) l ON true
            LEFT JOIN (
                SELECT subscriber_id, true AS active FROM subscriptions WHERE status = 'active'
            ) sub ON sub.subscriber_id = u.id
            WHERE l.geom IS NOT NULL
        ");

        DB::statement("
            CREATE OR REPLACE VIEW vw_pending_holds AS
            SELECT
                h.id AS hold_id,
                h.wallet_id,
                w.user_id,
                h.amount,
                h.reason,
                h.status,
                h.created_at
            FROM holds h
            JOIN wallets w ON w.id = h.wallet_id
            WHERE h.status = 'held'
        ");

        DB::statement("
            CREATE OR REPLACE VIEW vw_payouts_due AS
            SELECT
                p.id AS payment_id,
                p.payee_id,
                p.amount,
                p.currency,
                p.status,
                p.created_at
            FROM payments p
            WHERE p.status = 'settled' AND p.payee_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_payouts_due');
        DB::statement('DROP VIEW IF EXISTS vw_pending_holds');
        DB::statement('DROP VIEW IF EXISTS vw_available_technicians');
    }
};
