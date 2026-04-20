<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->decimal('amount', 10, 2);
            $table->boolean('paid')->default(false);
            $table->timestampTz('paid_at')->nullable();
            $table->uuid('payment_id')->nullable();

            $table->foreign('request_id')->references('id')->on('client_requests')->cascadeOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostics');
    }
};
