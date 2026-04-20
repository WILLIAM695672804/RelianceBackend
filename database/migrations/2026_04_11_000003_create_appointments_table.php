<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->uuid('technician_id');
            $table->timestampTz('scheduled_start');
            $table->timestampTz('scheduled_end')->nullable();
            $table->uuid('location_id')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, confirmed, in_progress, completed, cancelled, no_show
            $table->text('notes')->nullable();
            $table->timestampTz('assigned_at')->useCurrent();
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('client_requests')->onDelete('cascade');
            $table->foreign('technician_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
            $table->index(['technician_id', 'scheduled_start']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
