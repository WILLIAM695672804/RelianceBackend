<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->uuid('issuer_id');
            $table->decimal('total_amount', 12, 2);
            $table->jsonb('breakdown')->nullable();
            $table->string('status')->default('draft'); // draft, sent, accepted, rejected, expired
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('client_requests')->onDelete('cascade');
            $table->foreign('issuer_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
