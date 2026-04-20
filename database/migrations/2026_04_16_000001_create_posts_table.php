<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('author_id');
            $table->text('content')->nullable();
            $table->jsonb('attachments')->nullable();
            $table->timestampTz('created_at')->default(DB::raw('now()'));

            $table->foreign('author_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Index full-text search en français
        DB::statement("CREATE INDEX idx_posts_content_gin ON posts USING gin (to_tsvector('french', coalesce(content, '')))");
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
