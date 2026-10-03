<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Game;
use App\Models\ScoutReport;
use App\Models\Team;
use App\Models\User;
use App\Modules\Transfer\Services\ScoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 20: ScoutingService::startSearch() re-checks "no search in progress"
 * inside the transaction with the game row locked — a double-POST can't
 * start two parallel searches.
 */
class ScoutSearchLockTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    public function test_start_search_creates_report(): void
    {
        $service = app(ScoutingService::class);

        $report = $service->startSearch($this->game, ['position' => 'any_forward']);

        $this->assertSame(ScoutReport::STATUS_SEARCHING, $report->status);
        $this->assertSame($this->game->id, $report->game_id);
    }

    public function test_second_start_search_throws(): void
    {
        $service = app(ScoutingService::class);
        $service->startSearch($this->game, ['position' => 'any_forward']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('messages.scout_already_searching');

        $service->startSearch($this->game, ['position' => 'any_midfielder']);
    }

    public function test_only_one_searching_report_exists(): void
    {
        $service = app(ScoutingService::class);
        $service->startSearch($this->game, ['position' => 'any_forward']);

        try {
            $service->startSearch($this->game, ['position' => 'any_midfielder']);
            $this->fail('Expected DomainException');
        } catch (\DomainException $e) {
            // expected
        }

        $this->assertSame(1, ScoutReport::where('game_id', $this->game->id)
            ->where('status', ScoutReport::STATUS_SEARCHING)
            ->count());
    }
}
