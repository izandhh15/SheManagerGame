<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\DTOs\AffiliateSackDecision;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\AffiliateFirstTeamSackProcessor;
use App\Modules\Season\Services\AffiliateFirstTeamSackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Affiliate career ("Carrera con Filiales") sack rule: the user manages only
 * the reserve side; at the season rollover, if the AI-managed first team was
 * relegated, finished in the relegation zone, or ended far below the board's
 * objective, the board sacks its coach and hands the first team to the user.
 */
class AffiliateFirstTeamSackTest extends TestCase
{
    use RefreshDatabase;

    public function test_relegation_zone_triggers_sack_and_promotion(): void
    {
        [$game, $parent, $reserve] = $this->buildScenario(position: 16, reputation: 'local');

        $decision = $this->service()->evaluate($game->refresh());

        $this->assertNotNull($decision);
        $this->assertSame(AffiliateSackDecision::REASON_RELEGATION_ZONE, $decision->reason);
        $this->assertSame($parent->id, $decision->parentTeam->id);
        $this->assertSame('Test Coach', $decision->coachName);
        $this->assertSame(16, $decision->finalPosition);
    }

    public function test_processor_switches_game_to_first_team_and_notifies(): void
    {
        [$game, $parent, $reserve] = $this->buildScenario(position: 15, reputation: 'local');

        $data = new SeasonTransitionData(oldSeason: '2027', newSeason: '2028', competitionId: 'ESP2');
        $this->processor()->process($game->refresh(), $data);

        $game = $game->refresh();
        $this->assertSame($parent->id, $game->team_id, 'User should now manage the first team.');
        $this->assertSame($reserve->id, $game->reserve_team_id, 'Filial link must point at the reserve side.');
        $this->assertSame('ESP1', $game->competition_id);
        $this->assertSame('ESP1', $data->competitionId, 'Setup pipeline must build the new season around the first-team league.');
        $this->assertNull($game->season_goal);

        $notification = GameNotification::where('game_id', $game->id)
            ->where('type', 'affiliate_first_team_promotion')
            ->first();
        $this->assertNotNull($notification, 'The promotion must be celebrated with a notification.');
        $this->assertStringContainsString('Test Coach', $notification->message);
        $this->assertStringContainsString($parent->name, $notification->title);
    }

    public function test_processor_uses_generic_coach_name_when_unknown(): void
    {
        [$game, $parent] = $this->buildScenario(position: 16, reputation: 'local', coachName: null);

        $data = new SeasonTransitionData(oldSeason: '2027', newSeason: '2028', competitionId: 'ESP2');
        $this->processor()->process($game->refresh(), $data);

        $notification = GameNotification::where('game_id', $game->id)
            ->where('type', 'affiliate_first_team_promotion')
            ->first();
        $this->assertNotNull($notification);
        // Never invent a coach name: fall back to the generic label.
        $this->assertStringContainsString(__('game.affiliate_generic_coach'), $notification->message);
    }

    public function test_missed_objective_triggers_sack_for_big_club(): void
    {
        // Elite club in ESP1: board objective is the title (target 1).
        // 8th is further than 5 positions below target -> sacked.
        [$game] = $this->buildScenario(position: 8, reputation: 'elite');

        $decision = $this->service()->evaluate($game->refresh());

        $this->assertNotNull($decision);
        $this->assertSame(AffiliateSackDecision::REASON_MISSED_OBJECTIVE, $decision->reason);
    }

    public function test_actual_relegation_triggers_sack(): void
    {
        // 14th is outside ESP1's relegation zone ([15, 16]), but the
        // promotion/relegation pass already moved the club down to ESP2.
        [$game, $parent] = $this->buildScenario(position: 14, reputation: 'local', newLeagueId: 'ESP2');

        $decision = $this->service()->evaluate($game->refresh());

        $this->assertNotNull($decision);
        $this->assertSame(AffiliateSackDecision::REASON_RELEGATED, $decision->reason);
        $this->assertSame('ESP2', $decision->newLeagueId);
    }

    public function test_no_sack_when_first_team_meets_objective(): void
    {
        // Local club in ESP1: board objective is survival (target 15).
        // 10th is within target + 5 -> the coach stays, the user stays put.
        [$game, , $reserve] = $this->buildScenario(position: 10, reputation: 'local');

        $this->assertNull($this->service()->evaluate($game->refresh()));

        $data = new SeasonTransitionData(oldSeason: '2027', newSeason: '2028', competitionId: 'ESP2');
        $this->processor()->process($game->refresh(), $data);

        $this->assertSame($reserve->id, $game->refresh()->team_id, 'User should stay with the reserve side.');
        $this->assertSame('ESP2', $data->competitionId, 'Transition DTO must be untouched.');
        $this->assertSame(0, GameNotification::where('game_id', $game->id)
            ->where('type', 'affiliate_first_team_promotion')->count());
    }

    public function test_legacy_two_save_pairs_keep_old_behaviour(): void
    {
        [$game] = $this->buildScenario(position: 16, reputation: 'local');
        $otherGame = Game::factory()->create(['user_id' => $game->user_id]);
        $game->update(['linked_game_id' => $otherGame->id]);

        $this->assertNull($this->service()->evaluate($game->refresh()));
    }

    public function test_non_affiliate_games_are_ignored(): void
    {
        [$game] = $this->buildScenario(position: 16, reputation: 'local');
        $game->update(['pair_mode' => 'dual']);

        $this->assertNull($this->service()->evaluate($game->refresh()));
    }

    public function test_already_managing_first_team_is_ignored(): void
    {
        [$game, $parent] = $this->buildScenario(position: 16, reputation: 'local');
        $game->update(['team_id' => $parent->id]);

        $this->assertNull($this->service()->evaluate($game->refresh()));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function service(): AffiliateFirstTeamSackService
    {
        return app(AffiliateFirstTeamSackService::class);
    }

    private function processor(): AffiliateFirstTeamSackProcessor
    {
        return new AffiliateFirstTeamSackProcessor(
            $this->service(),
            app(NotificationService::class),
        );
    }

    /**
     * Builds an affiliate-career save: the user manages $reserve in ESP2,
     * the AI manages $parent in ESP1 with a final league position.
     *
     * @return array{Game, Team, Team}
     */
    private function buildScenario(
        int $position,
        string $reputation,
        ?string $coachName = 'Test Coach',
        ?string $newLeagueId = null,
    ): array {
        $esp1 = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);
        $esp2 = Competition::factory()->league()->create([
            'id' => 'ESP2',
            'name' => 'Primera RFEF (test)',
            'country' => 'ES',
            'tier' => 2,
        ]);

        $parent = Team::factory()->create([
            'country' => 'ES',
            'manager_name' => $coachName,
        ]);
        ClubProfile::create(['team_id' => $parent->id, 'reputation_level' => $reputation]);

        $reserve = Team::factory()->create([
            'country' => 'ES',
            'parent_team_id' => $parent->id,
        ]);

        $user = User::factory()->create();
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'game_mode' => Game::MODE_CAREER,
            'country' => 'ES',
            'team_id' => $reserve->id,
            'reserve_team_id' => null,
            'competition_id' => 'ESP2',
            'season' => '2027',
            'pair_mode' => 'affiliate',
        ]);

        GameStanding::create([
            'game_id' => $game->id,
            'competition_id' => 'ESP1',
            'team_id' => $parent->id,
            'position' => $position,
            'played' => 30,
            'points' => 20,
        ]);

        // Post promotion/relegation state: where the first team plays next season.
        CompetitionEntry::create([
            'game_id' => $game->id,
            'competition_id' => $newLeagueId ?? 'ESP1',
            'team_id' => $parent->id,
            'entry_round' => 1,
        ]);

        return [$game, $parent, $reserve];
    }
}
