<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->uuid('opened_by');
            $table->uuid('against');
            $table->string('reason');
            $table->text('description');
            $table->string('status')->default('open'); // open, under_review, resolved, closed
            $table->text('resolution')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('client_requests');
            $table->foreign('opened_by')->references('id')->on('users');
            $table->foreign('against')->references('id')->on('users');
        });

        Schema::create('dispute_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dispute_id');
            $table->uuid('sender_id');
            $table->text('message');
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->foreign('dispute_id')->references('id')->on('disputes')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_messages');
        Schema::dropIfExists('disputes');
    }
};
