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
use App\Modules\Season\Services\AffiliateMidSeasonSackService;
use App\Modules\Season\Services\SeasonGoalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Affiliate career ("Carrera con Filiales") mid-season sack: the user manages
 * only the reserve side; once the season is under way (8+ matchdays), if the
 * AI-managed first team sits in the relegation zone or clearly adrift of the
 * board's objective, the board sacks its coach mid-season and hands the first
 * team to the user "para salvar al equipo" (te cuelgan el marrón).
 *
 * The sack fires at most once per save: the season-end evaluation must not
 * trigger again afterwards.
 */
class AffiliateMidSeasonSackTest extends TestCase
{
    use RefreshDatabase;

    public function test_relegation_zone_at_matchday_10_triggers_midseason_sack(): void
    {
        [$game, $parent] = $this->buildScenario(position: 16, played: 10, reputation: 'local');

        $decision = $this->sackService()->evaluateMidSeason($game->refresh());

        $this->assertNotNull($decision);
        $this->assertSame(AffiliateSackDecision::REASON_RELEGATION_ZONE, $decision->reason);
        $this->assertSame($parent->id, $decision->parentTeam->id);
        $this->assertSame('Test Coach', $decision->coachName);
        $this->assertSame(16, $decision->finalPosition);
        // Mid-season there is no "next season": the user takes over in the
        // first team's current league.
        $this->assertSame('ESP1', $decision->newLeagueId);
    }

    public function test_trigger_switches_game_to_first_team_and_notifies(): void
    {
        [$game, $parent, $reserve] = $this->buildScenario(position: 16, played: 10, reputation: 'local');

        $this->assertTrue($this->midSeasonService()->trigger($game->refresh()));

        $game = $game->refresh();
        $this->assertSame($parent->id, $game->team_id, 'User should now manage the first team.');
        $this->assertSame($reserve->id, $game->reserve_team_id, 'Filial link must point at the reserve side.');
        $this->assertSame('ESP1', $game->competition_id, 'Dashboard must follow the first-team league.');
        $this->assertTrue($game->affiliate_midseason_sack, 'The save must be flagged so the sack never repeats.');
        $this->assertSame(Game::GOAL_SURVIVAL, $game->season_goal, 'The rescue job gets a fresh board objective.');

        $notification = GameNotification::where('game_id', $game->id)
            ->where('type', 'affiliate_midseason_sack')
            ->first();
        $this->assertNotNull($notification, 'The takeover must be announced with a notification.');
        $this->assertStringContainsString($parent->name, $notification->title);
        $this->assertStringContainsString('marrón', $notification->title);
        $this->assertStringContainsString('Test Coach', $notification->message);
        $this->assertStringContainsString('16.º', $notification->message);
        $this->assertStringContainsString('jornada 10', $notification->message);
        $this->assertSame(GameNotification::PRIORITY_MILESTONE, $notification->priority);
    }

    public function test_trigger_uses_generic_coach_name_when_unknown(): void
    {
        [$game] = $this->buildScenario(position: 16, played: 10, reputation: 'local', coachName: null);

        $this->assertTrue($this->midSeasonService()->trigger($game->refresh()));

        $notification = GameNotification::where('game_id', $game->id)
            ->where('type', 'affiliate_midseason_sack')
            ->first();
        $this->assertNotNull($notification);
        // Never invent a coach name: fall back to the generic label.
        $this->assertStringContainsString(__('game.affiliate_generic_coach'), $notification->message);
    }

    public function test_far_below_objective_triggers_midseason_sack_for_big_club(): void
    {
        // Elite club in ESP1: board objective is the title (target 1).
        // 10th at matchday 10 is further than 8 positions below target -> sacked.
        [$game] = $this->buildScenario(position: 10, played: 10, reputation: 'elite');

        $decision = $this->sackService()->evaluateMidSeason($game->refresh());

        $this->assertNotNull($decision);
        $this->assertSame(AffiliateSackDecision::REASON_MISSED_OBJECTIVE, $decision->reason);
    }

    public function test_no_sack_when_first_team_is_doing_well(): void
    {
        // Local club in ESP1: board objective is survival (target ~15).
        // 5th at matchday 10 -> the coach stays, the user stays put.
        [$game, , $reserve] = $this->buildScenario(position: 5, played: 10, reputation: 'local');

        $this->assertNull($this->sackService()->evaluateMidSeason($game->refresh()));
        $this->assertFalse($this->midSeasonService()->trigger($game->refresh()));

        $this->assertSame($reserve->id, $game->refresh()->team_id, 'User should stay with the reserve side.');
        $this->assertFalse($game->affiliate_midseason_sack);
        $this->assertSame(0, GameNotification::where('game_id', $game->id)
            ->where('type', 'affiliate_midseason_sack')->count());
    }

    public function test_no_sack_before_matchday_8(): void
    {
        // Rock bottom after 7 games: the board still waits, the season is
        // too young to judge.
        [$game] = $this->buildScenario(position: 16, played: 7, reputation: 'local');

        $this->assertNull($this->sackService()->evaluateMidSeason($game->refresh()));
        $this->assertFalse($this->midSeasonService()->trigger($game->refresh()));
    }

    public function test_near_miss_of_objective_does_not_trigger(): void
    {
        // Elite club 9th at matchday 10: exactly at the margin (target 1 + 8),
        // not beyond it -> the bar stays high, no sack.
        [$game] = $this->buildScenario(position: 9, played: 10, reputation: 'elite');

        $this->assertNull($this->sackService()->evaluateMidSeason($game->refresh()));
    }

    public function test_midseason_sack_does_not_repeat_at_season_end(): void
    {
        [$game, $parent] = $this->buildScenario(position: 16, played: 10, reputation: 'local');

        $this->assertTrue($this->midSeasonService()->trigger($game->refresh()));

        // Season ends with the first team still in the relegation zone…
        GameStanding::where('game_id', $game->id)
            ->where('team_id', $parent->id)
            ->update(['played' => 30, 'position' => 16]);

        // …but the season-end evaluation must not fire again.
        $this->assertNull(
            $this->sackService()->evaluate($game->refresh()),
            'Season-end sack must not re-fire after a mid-season sack.'
        );
        $this->assertNull(
            $this->sackService()->evaluateMidSeason($game->refresh()),
            'Mid-season sack must not fire twice.'
        );

        // The season-end processor is a no-op: no second switch, no second news.
        $data = new SeasonTransitionData(oldSeason: '2027', newSeason: '2028', competitionId: 'ESP1');
        (new AffiliateFirstTeamSackProcessor(
            $this->sackService(),
            app(NotificationService::class),
        ))->process($game->refresh(), $data);

        $this->assertSame($parent->id, $game->refresh()->team_id);
        $this->assertSame('ESP1', $data->competitionId, 'Transition DTO must be untouched.');
        $this->assertSame(0, GameNotification::where('game_id', $game->id)
            ->where('type', 'affiliate_first_team_promotion')->count());
        $this->assertSame(1, GameNotification::where('game_id', $game->id)
            ->where('type', 'affiliate_midseason_sack')->count());
    }

    public function test_non_affiliate_games_are_ignored(): void
    {
        [$game] = $this->buildScenario(position: 16, played: 10, reputation: 'local');
        $game->update(['pair_mode' => 'dual']);

        $this->assertNull($this->sackService()->evaluateMidSeason($game->refresh()));
        $this->assertFalse($this->midSeasonService()->trigger($game->refresh()));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function sackService(): AffiliateFirstTeamSackService
    {
        return app(AffiliateFirstTeamSackService::class);
    }

    private function midSeasonService(): AffiliateMidSeasonSackService
    {
        return new AffiliateMidSeasonSackService(
            $this->sackService(),
            app(SeasonGoalService::class),
            app(NotificationService::class),
        );
    }

    /**
     * Builds an affiliate-career save: the user manages $reserve in ESP2,
     * the AI manages $parent in ESP1 with a live league table ($played games).
     *
     * @return array{Game, Team, Team}
     */
    private function buildScenario(
        int $position,
        int $played,
        string $reputation,
        ?string $coachName = 'Test Coach',
    ): array {
        $esp1 = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F (test)',
            'country' => 'ES',
            'tier' => 1,
        ]);
        Competition::factory()->league()->create([
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
            'played' => $played,
            'points' => 20,
        ]);

        return [$game, $parent, $reserve];
    }
}
