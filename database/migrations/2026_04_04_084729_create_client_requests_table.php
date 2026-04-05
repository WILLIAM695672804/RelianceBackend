<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_requests', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $blueprint->string('subject');
            $blueprint->text('description');
            $blueprint->string('category')->nullable();
            $blueprint->jsonb('meta')->nullable(); // stored as JSON
            $blueprint->string('status')->default('pending'); // pending, analyzed, matching, proposed, accepted, completed, cancelled
            $blueprint->decimal('budget_min', 12, 2)->nullable();
            $blueprint->decimal('budget_max', 12, 2)->nullable();
            $blueprint->timestamp('preferred_time')->nullable();
            $blueprint->timestamps();
            $blueprint->softDeletes();
        });

        // Add PostGIS point for request location
        DB::statement('ALTER TABLE client_requests ADD COLUMN location GEOMETRY(POINT, 4326)');
        DB::statement('CREATE INDEX client_requests_location_idx ON client_requests USING GIST(location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('client_requests');
    }
};
