<?php
namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;
use App\Modules\Gamification\Models\Badge;
use App\Modules\Gamification\Services\BadgeAwardService;
use App\Modules\Matching\Models\ClientRequest;
use App\Modules\Matching\Models\Proposal;

class BadgeAwardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);
    }

    public function test_awards_top_performer_badge(): void
    {
        $tech = User::forceCreate([
            'name' => 'TopTech', 'email' => 't-' . Str::uuid() . '@test.com', 'password' => bcrypt('pwd'),
            'rating' => 4.8, 'num_reviews' => 25,
        ]);
        $tech->assignRole('technicien');

        Badge::create([
            'code' => 'top_performer', 'title' => 'Top Performer', 'description' => 'High rating',
            'icon_ref' => 'emoji_events',
            'criteria' => ['type' => 'avg_rating', 'min_rating' => 4.5, 'min_reviews' => 20],
        ]);

        $service = new BadgeAwardService();
        $awarded = $service->evaluate($tech);

        $this->assertContains('top_performer', $awarded);
        $this->assertTrue($tech->badges()->where('code', 'top_performer')->exists());
    }

    public function test_does_not_award_badge_twice(): void
    {
        $tech = User::forceCreate([
            'name' => 'TopTech2', 'email' => 't2-' . Str::uuid() . '@test.com', 'password' => bcrypt('pwd'),
            'rating' => 4.8, 'num_reviews' => 25,
        ]);
        $tech->assignRole('technicien');

        Badge::create([
            'code' => 'top_perf_2', 'title' => 'Top Performer', 'description' => 'High rating',
            'icon_ref' => 'emoji_events',
            'criteria' => ['type' => 'avg_rating', 'min_rating' => 4.5, 'min_reviews' => 20],
        ]);

        $service = new BadgeAwardService();
        $first = $service->evaluate($tech);
        $second = $service->evaluate($tech);

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
    }
}
