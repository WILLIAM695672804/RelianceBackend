<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('technician_profiles');
        Schema::dropIfExists('agency_profiles');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // On reverse, we just put them back (as empty shells)
        Schema::create('technician_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->text('bio')->nullable();
            $table->json('competences')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('agency_name');
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
};
