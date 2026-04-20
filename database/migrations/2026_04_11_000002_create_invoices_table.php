<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quote_id')->nullable();
            $table->uuid('client_id');
            $table->uuid('issuer_id');
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->string('reference')->unique()->nullable();
            $table->string('external_pdf_ref')->nullable();
            $table->string('status')->default('unpaid'); // unpaid, paid, cancelled, refunded
            $table->timestamps();

            $table->foreign('quote_id')->references('id')->on('quotes')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('issuer_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
