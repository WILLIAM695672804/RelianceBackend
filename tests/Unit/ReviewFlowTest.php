<?php
namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;
use App\Modules\Gamification\Models\Review;
use App\Modules\Gamification\Services\ReputationService;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Models\Proposal;

class ReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_reputation_recalculates_after_review(): void
    {
        $client = User::create([
            'name' => 'Test Client', 'email' => 'c-' . Str::uuid() . '@test.com', 'password' => bcrypt('pwd'),
        ]);
        $client->assignRole('client');

        $tech = User::create([
            'name' => 'Test Tech', 'email' => 't-' . Str::uuid() . '@test.com', 'password' => bcrypt('pwd'),
            'rating' => 0, 'num_reviews' => 0,
        ]);
        $tech->assignRole('technicien');

        $request = ClientRequest::create([
            'client_id' => $client->id, 'title' => 'Test request', 'description' => 'Test', 'status' => 'completed',
        ]);

        Proposal::create([
            'request_id' => $request->id, 'technician_id' => $tech->id, 'price' => 5000, 'status' => 'accepted',
        ]);

        $review = Review::create([
            'reviewer_id' => $client->id, 'target_id' => $tech->id, 'request_id' => $request->id,
            'rating' => 5, 'comment' => 'Excellent work',
        ]);

        $service = new ReputationService();
        $service->recalculateAfterReview($review);

        $tech->refresh();
        $this->assertEquals(5.00, (float) $tech->rating);
        $this->assertEquals(1, $tech->num_reviews);
        $this->assertDatabaseHas('reputation_changes', [
            'user_id' => $tech->id, 'change' => 10,
        ]);
    }
}
