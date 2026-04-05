<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_request_id')->constrained('client_requests')->onDelete('cascade');
            $table->foreignUuid('technician_id')->constrained('users')->onDelete('cascade');
            $table->decimal('total_score', 8, 2);
            $table->jsonb('score_details'); // breakdown per criterion
            $table->boolean('was_proposed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_logs');
    }
};
