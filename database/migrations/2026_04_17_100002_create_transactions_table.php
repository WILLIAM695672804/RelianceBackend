<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wallet_id');
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 14, 2)->nullable();
            $table->string('type'); // credit, debit, hold, release, fee
            $table->uuid('related_payment')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->jsonb('meta')->nullable();

            $table->foreign('wallet_id')->references('id')->on('wallets')->cascadeOnDelete();
            $table->foreign('related_payment')->references('id')->on('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
