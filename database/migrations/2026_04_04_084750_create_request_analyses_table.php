<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_request_id')->constrained('client_requests')->onDelete('cascade');
            $table->jsonb('extracted_skills'); // Array of required skill slugs
            $table->string('intensity')->default('medium'); // low, medium, high, emergency
            $table->integer('estimated_duration_minutes')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_analyses');
    }
};
