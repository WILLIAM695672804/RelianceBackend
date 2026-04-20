<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->uuid('sender_id');
            $table->text('body')->nullable();
            $table->jsonb('attachments')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));
            $table->boolean('delivered')->default(false);
            $table->timestampTz('read_at')->nullable();

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
        });

        DB::statement('CREATE INDEX idx_messages_conversation_created ON messages(conversation_id, created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
