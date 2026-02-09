<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');
            $table->string('type'); // cni, passport, rccm, etc.
            $table->string('file_path');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();

            $table->uuid('validated_by')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};