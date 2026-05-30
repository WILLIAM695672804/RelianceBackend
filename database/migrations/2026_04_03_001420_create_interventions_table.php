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
        Schema::create('interventions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('job_id');
            $table->uuid('technician_id');
            $table->uuid('request_id');
            $table->string('type')->default('standard');
            $table->string('status')->default('planned');
            $table->text('description')->nullable();
            $table->text('work_performed')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('materials_used')->nullable();
            $table->decimal('duration_hours', 6, 2)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('technician_jobs')->onDelete('cascade');
            $table->foreign('technician_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('request_id')->references('id')->on('client_requests')->onDelete('cascade');
            $table->index(['technician_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
