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
        Schema::create('technician_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('skill_id')->constrained('skills')->onDelete('cascade');
            
            $table->smallInteger('proficiency')->nullable(); // 0 to 100
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('travel_fee', 10, 2)->default(0);
            $table->json('meta')->nullable();

            $table->timestamps();
            
            $table->unique(['user_id', 'skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technician_skills');
    }
};
