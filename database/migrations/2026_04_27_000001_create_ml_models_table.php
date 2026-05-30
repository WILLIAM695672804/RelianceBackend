<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('version')->nullable();
            $table->string('model_ref')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->boolean('deployed')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ml_training_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('model_id');
            $table->string('dataset_ref')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->timestamp('run_at')->useCurrent();
            $table->text('notes')->nullable();

            $table->foreign('model_id')->references('id')->on('ml_models')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_training_logs');
        Schema::dropIfExists('ml_models');
    }
};
