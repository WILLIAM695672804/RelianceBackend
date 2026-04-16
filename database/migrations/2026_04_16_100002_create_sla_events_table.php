<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id')->nullable();
            $table->string('event_key')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->jsonb('payload')->nullable();

            $table->foreign('request_id')->references('id')->on('client_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_events');
    }
};
