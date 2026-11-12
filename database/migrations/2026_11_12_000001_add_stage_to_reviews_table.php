<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Notation « avant et après » la prestation (cahier des charges §4.3) :
 * un avis de chaque étape par client et par demande.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE reviews ADD COLUMN IF NOT EXISTS stage varchar(10) NOT NULL DEFAULT 'post'");
        DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_stage_check CHECK (stage IN ('pre', 'post'))");
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_reviewer_id_request_id_unique');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_reviewer_request_stage_unique UNIQUE (reviewer_id, request_id, stage)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_reviewer_request_stage_unique');
        DB::statement("DELETE FROM reviews WHERE stage = 'pre'");
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_reviewer_id_request_id_unique UNIQUE (reviewer_id, request_id)');
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT IF EXISTS reviews_stage_check');
        DB::statement('ALTER TABLE reviews DROP COLUMN IF EXISTS stage');
    }
};
