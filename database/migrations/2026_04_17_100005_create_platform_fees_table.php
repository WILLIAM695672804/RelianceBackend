<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('fee_type'); // percentage, fixed
            $table->decimal('value', 10, 4);
            $table->string('applies_to');
            $table->boolean('active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->jsonb('meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_fees');
    }
};
