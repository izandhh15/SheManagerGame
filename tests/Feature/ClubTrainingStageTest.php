<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Services\TrainingStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Club preseason training stage (concentración): organized from the
 * preseason setup screen, charged to the club's transfer budget, one per
 * season. Applies fitness/morale effects to the squad like the
 * national-team stage.
 */
class ClubTrainingStageTest extends TestCase
{
    use RefreshDatabase;

    private function makeGame(string $mode = Game::MODE_CAREER): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Stage WFC', 'country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'game_mode' => $mode,
            'season' => '2026',
        ]);

        GameInvestment::create([
            'game_id' => $game->id,
            'season' => $game->season,
            'transfer_budget' => 10_000_000_00, // €10M in cents
            'scouting_tier' => 1,
        ]);

        $players = GamePlayer::factory()->count(5)->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
        ]);

        return [$user, $team, $game, $players];
    }

    private function config(): array
    {
        return [
            'destination' => 'Portugal',
            'duration' => '1w',
            'intensity' => 'balanced',
            'focus' => 'physical',
        ];
    }

    public function test_confirm_charges_budget_and_applies_effects(): void
    {
        [$user, $team, $game, $players] = $this->makeGame();
        $service = app(TrainingStageService::class);

        $before = (int) $game->currentInvestment->transfer_budget;
        $fitnessBefore = (int) $players->first()->refresh()->fitness;

        $result = $service->confirmClubStage($game->refresh(), $this->config());

        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('cost', $result);
        $this->assertGreaterThan(0, $result['cost']);

        // Budget charged (cents). NB: refresh() can poison the
        // currentInvestment relation (see TrainingStageService), so drop it.
        $game->refresh()->unsetRelation('currentInvestment');
        $this->assertSame(
            $before - $result['cost'] * 100,
            (int) $game->currentInvestment->transfer_budget
        );

        // Config persisted with the season.
        $stored = $game->refresh()->training_stage;
        $this->assertSame('Portugal', $stored['destination']);
        $this->assertSame('2026', $stored['season']);

        // Squad effects applied (balanced+physical 1w = +11 fitness).
        $this->assertGreaterThan($fitnessBefore, (int) $players->first()->refresh()->fitness);
    }

    public function test_second_stage_same_season_is_blocked(): void
    {
        [$user, $team, $game] = $this->makeGame();
        $service = app(TrainingStageService::class);

        $this->assertTrue($service->confirmClubStage($game->refresh(), $this->config())['ok']);
        $second = $service->confirmClubStage($game->refresh(), $this->config());

        $this->assertFalse($second['ok']);
    }

    public function test_stage_allowed_again_next_season(): void
    {
        [$user, $team, $game] = $this->makeGame();
        $service = app(TrainingStageService::class);

        $this->assertTrue($service->confirmClubStage($game->refresh(), $this->config())['ok']);

        $game->update(['season' => '2027']);
        GameInvestment::create([
            'game_id' => $game->id,
            'season' => '2027',
            'transfer_budget' => 10_000_000_00,
            'scouting_tier' => 1,
        ]);
        $this->assertTrue($service->confirmClubStage($game->refresh(), $this->config())['ok']);
    }

    public function test_tournament_mode_is_rejected(): void
    {
        [$user, $team, $game] = $this->makeGame(Game::MODE_TOURNAMENT);
        $service = app(TrainingStageService::class);

        $this->assertFalse($service->confirmClubStage($game->refresh(), $this->config())['ok']);
    }

    public function test_http_endpoint_organizes_stage(): void
    {
        [$user, $team, $game] = $this->makeGame();

        $response = $this->actingAs($user)->post(
            route('game.preseason-setup.stage', $game->id),
            $this->config()
        );

        $response->assertRedirect(route('game.preseason-setup', $game->id));
        $response->assertSessionHas('success');
        $this->assertSame('Portugal', $game->refresh()->training_stage['destination']);
    }

    public function test_http_endpoint_404_in_tournament_mode(): void
    {
        [$user, $team, $game] = $this->makeGame(Game::MODE_TOURNAMENT);

        $response = $this->actingAs($user)->post(
            route('game.preseason-setup.stage', $game->id),
            $this->config()
        );

        $response->assertNotFound();
    }

    public function test_http_endpoint_rejects_invalid_config(): void
    {
        [$user, $team, $game] = $this->makeGame();

        $response = $this->actingAs($user)->post(
            route('game.preseason-setup.stage', $game->id),
            ['destination' => 'Portugal', 'duration' => '9w', 'intensity' => 'balanced', 'focus' => 'physical']
        );

        $response->assertRedirect();
        $response->assertSessionHas('errors');
        $this->assertNull($game->refresh()->training_stage);
    }
}
