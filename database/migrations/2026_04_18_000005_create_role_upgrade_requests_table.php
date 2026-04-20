<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_upgrade_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('requested_role')->default('technicien');
            $table->text('motivation')->nullable();
            $table->json('skills')->nullable();
            $table->text('bio')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->uuid('reviewed_by')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('reviewed_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_upgrade_requests');
    }
};
