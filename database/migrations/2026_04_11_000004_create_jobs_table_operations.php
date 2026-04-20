<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->uuid('technician_id');
            $table->uuid('appointment_id')->nullable();
            $table->string('status')->default('pending'); // pending, en_route, arrived, in_progress, completed, cancelled
            $table->jsonb('route')->nullable();
            $table->integer('estimated_travel_seconds')->nullable();
            $table->jsonb('phases')->nullable(); // phases de réalisation définies par le technicien
            $table->text('completion_notes')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('client_requests')->onDelete('cascade');
            $table->foreign('technician_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('set null');
            $table->index(['technician_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_jobs');
    }
};
