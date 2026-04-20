<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wallet_id');
            $table->decimal('amount', 14, 2);
            $table->string('reason')->nullable();
            $table->string('status')->default('held');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('released_at')->nullable();
            $table->uuid('related_payment')->nullable();

            $table->foreign('wallet_id')->references('id')->on('wallets')->cascadeOnDelete();
            $table->foreign('related_payment')->references('id')->on('payments')->nullOnDelete();
            $table->index(['wallet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};
