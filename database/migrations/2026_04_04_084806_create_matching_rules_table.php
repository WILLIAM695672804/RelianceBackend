<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_rules', function (Blueprint $table) {
            $table->id();
            $table->string('criterion'); // distance, rating, skill_proficiency, availability, success_rate
            $table->decimal('weight', 5, 2)->default(1.0);
            $table->boolean('is_active')->default(true);
            $table->jsonb('config')->nullable(); // threshold values, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_rules');
    }
};
