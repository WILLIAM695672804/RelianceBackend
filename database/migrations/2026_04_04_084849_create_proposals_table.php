<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_request_id')->constrained('client_requests')->onDelete('cascade');
            $table->foreignUuid('technician_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('sent'); // sent, view, accepted, refused, expired
            $table->decimal('score', 8, 2);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
