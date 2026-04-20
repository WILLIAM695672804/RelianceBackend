<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('follower_id');
            $table->uuid('followee_id');
            $table->timestampTz('created_at')->default(DB::raw('now()'));

            $table->foreign('follower_id')->references('id')->on('users');
            $table->foreign('followee_id')->references('id')->on('users');
            $table->unique(['follower_id', 'followee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
