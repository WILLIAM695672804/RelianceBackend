<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('target_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('request_id')->nullable()->constrained('client_requests')->onDelete('set null');
            $table->unsignedSmallInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['reviewer_id', 'request_id']);
        });

        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_check CHECK (rating >= 1 AND rating <= 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
