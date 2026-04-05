<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migrate matching_rules from the old criterion/weight/is_active/config schema
 * to the plan's name/priority/enabled/rule_json schema.
 *
 * Since matching_rules contains only seeded data (no user content),
 * we drop and recreate the table cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the old table (seeded data only, safe to recreate)
        Schema::dropIfExists('matching_rules');

        // Recreate with the plan's schema
        Schema::create('matching_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->integer('priority')->default(100);
            $table->boolean('enabled')->default(true);
            $table->json('rule_json');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_rules');

        // Restore old schema
        Schema::create('matching_rules', function (Blueprint $table) {
            $table->id();
            $table->string('criterion');
            $table->decimal('weight', 5, 2)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->jsonb('config')->nullable();
            $table->timestamps();
        });
    }
};
