<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('from_currency'); // ex: 'EUR'
            $table->string('to_currency');   // ex: 'XAF'
            $table->decimal('rate', 12, 4);
            $table->boolean('active')->default(true);
            $table->uuid('set_by')->nullable();
            $table->timestamps();

            $table->foreign('set_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
