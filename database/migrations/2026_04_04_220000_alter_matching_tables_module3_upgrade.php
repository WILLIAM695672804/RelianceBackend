<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module 3 Schema Upgrade — aligns existing tables with the final plan.
 *
 * Changes:
 *  - client_requests: rename user_id → client_id, subject → title,
 *    drop budget_min/max/preferred_time, add address_id, detected_category,
 *    diagnostic_fee, photos, metadata
 *  - proposals: rename client_request_id → request_id, drop score, add price/message
 *  - matching_logs: rename client_request_id → request_id, refactor score columns
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. client_requests ───────────────────────────────────────────
        if (Schema::hasTable('client_requests')) {
            Schema::table('client_requests', function (Blueprint $table) {
                // Rename user_id → client_id (FK already exists, drop before rename)
                if (Schema::hasColumn('client_requests', 'user_id')) {
                    $table->dropForeign(['user_id']);
                    $table->renameColumn('user_id', 'client_id');
                }

                // Rename subject → title
                if (Schema::hasColumn('client_requests', 'subject')) {
                    $table->renameColumn('subject', 'title');
                }

                // Drop old columns that are replaced
                $toDrop = array_filter(
                    ['category', 'meta', 'budget_min', 'budget_max', 'preferred_time', 'location'],
                    fn($col) => Schema::hasColumn('client_requests', $col)
                );
                if (!empty($toDrop)) {
                    $table->dropColumn($toDrop);
                }

                // Add new columns if missing
                if (!Schema::hasColumn('client_requests', 'address_id')) {
                    $table->foreignUuid('address_id')->nullable()->constrained('locations')->nullOnDelete();
                }
                if (!Schema::hasColumn('client_requests', 'detected_category')) {
                    $table->foreignUuid('detected_category')->nullable()->constrained('skills')->nullOnDelete();
                }
                if (!Schema::hasColumn('client_requests', 'diagnostic_fee')) {
                    $table->decimal('diagnostic_fee', 12, 2)->nullable();
                }
                if (!Schema::hasColumn('client_requests', 'photos')) {
                    $table->jsonb('photos')->default('[]');
                }
                if (!Schema::hasColumn('client_requests', 'metadata')) {
                    $table->jsonb('metadata')->nullable();
                }
            });

            // Re-add client_id FK (after rename)
            Schema::table('client_requests', function (Blueprint $table) {
                if (Schema::hasColumn('client_requests', 'client_id')) {
                    $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
                }
            });

            // Add PostGIS geom column (replace old GEOMETRY location)
            if (!Schema::hasColumn('client_requests', 'geom')) {
                DB::statement('ALTER TABLE client_requests ADD COLUMN IF NOT EXISTS geom GEOMETRY(POINT, 4326)');
                DB::statement('CREATE INDEX IF NOT EXISTS idx_client_requests_geom ON client_requests USING GIST(geom)');
            }
        }

        // ─── 2. proposals ─────────────────────────────────────────────────
        if (Schema::hasTable('proposals')) {
            Schema::table('proposals', function (Blueprint $table) {
                // Rename client_request_id → request_id
                if (Schema::hasColumn('proposals', 'client_request_id')) {
                    $table->dropForeign(['client_request_id']);
                    $table->renameColumn('client_request_id', 'request_id');
                }

                // Drop old score column, add price + message
                if (Schema::hasColumn('proposals', 'score')) {
                    $table->dropColumn('score');
                }
                if (!Schema::hasColumn('proposals', 'price')) {
                    $table->decimal('price', 12, 2)->default(0);
                }
                if (!Schema::hasColumn('proposals', 'message')) {
                    $table->text('message')->nullable();
                }

                // Update status default (was 'sent')
                $table->string('status')->default('pending')->change();
            });

            // Re-add request_id FK
            Schema::table('proposals', function (Blueprint $table) {
                if (Schema::hasColumn('proposals', 'request_id')) {
                    $table->foreign('request_id')->references('id')->on('client_requests')->onDelete('cascade');
                }
            });
        }

        // ─── 3. matching_logs ─────────────────────────────────────────────
        if (Schema::hasTable('matching_logs')) {
            Schema::table('matching_logs', function (Blueprint $table) {
                // Rename client_request_id → request_id
                if (Schema::hasColumn('matching_logs', 'client_request_id')) {
                    $table->dropForeign(['client_request_id']);
                    $table->renameColumn('client_request_id', 'request_id');
                }

                // Drop old columns, add new ones
                $toDrop = array_filter(
                    ['technician_id', 'total_score', 'score_details', 'was_proposed'],
                    fn($col) => Schema::hasColumn('matching_logs', $col)
                );
                if (!empty($toDrop)) {
                    $table->dropColumn($toDrop);
                }

                if (!Schema::hasColumn('matching_logs', 'chosen_technician_id')) {
                    $table->foreignUuid('chosen_technician_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('matching_logs', 'candidates')) {
                    $table->jsonb('candidates')->default('[]');
                }
                if (!Schema::hasColumn('matching_logs', 'scoring_details')) {
                    $table->jsonb('scoring_details')->nullable();
                }
            });

            // Re-add request_id FK
            Schema::table('matching_logs', function (Blueprint $table) {
                if (Schema::hasColumn('matching_logs', 'request_id')) {
                    $table->foreign('request_id')->references('id')->on('client_requests')->onDelete('cascade');
                }
            });
        }
        // ─── 4. request_analyses ──────────────────────────────────────────
        if (Schema::hasTable('request_analyses')) {
            Schema::table('request_analyses', function (Blueprint $table) {
                // Rename client_request_id → request_id
                if (Schema::hasColumn('request_analyses', 'client_request_id')) {
                    $table->dropForeign(['client_request_id']);
                    $table->renameColumn('client_request_id', 'request_id');
                }

                // Drop old columns
                $toDrop = array_filter(
                    ['extracted_skills', 'intensity', 'estimated_duration_minutes', 'summary'],
                    fn($col) => Schema::hasColumn('request_analyses', $col)
                );
                if (!empty($toDrop)) {
                    $table->dropColumn($toDrop);
                }

                // Add new columns
                if (!Schema::hasColumn('request_analyses', 'analyzed_at')) {
                    $table->timestamp('analyzed_at')->nullable();
                }
                if (!Schema::hasColumn('request_analyses', 'estimated_skills')) {
                    $table->jsonb('estimated_skills')->default('[]');
                }
                if (!Schema::hasColumn('request_analyses', 'estimated_difficulty')) {
                    $table->string('estimated_difficulty')->default('moderate');
                }
                if (!Schema::hasColumn('request_analyses', 'suggested_price')) {
                    $table->decimal('suggested_price', 12, 2)->nullable();
                }
                if (!Schema::hasColumn('request_analyses', 'payload')) {
                    $table->jsonb('payload')->nullable();
                }
            });

            // Re-add request_id FK
            Schema::table('request_analyses', function (Blueprint $table) {
                if (Schema::hasColumn('request_analyses', 'request_id')) {
                    $table->foreign('request_id')->references('id')->on('client_requests')->onDelete('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        // Reversing a rename+drop migration is complex — manual rollback recommended
        // This is a one-way migration intended for the Module 3 upgrade
    }
};
