<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\PromotionRelegationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end tests for the rewritten {@see PromotionRelegationProcessor}.
 *
 * Each test seeds a full Spain-shaped game state (ESP1 / ESP2 / ESP3A /
 * ESP3B / ESP3C), runs the processor, then asserts the on-disk
 * CompetitionEntry / GameStanding / Game.competition_id reflect the
 * planner's intended moves.
 *
 * Pure planner logic is exercised by {@see \Tests\Unit\CountryPromotionRelegationPlannerTest};
 * this file only covers the DB-touching plumbing: snapshot read,
 * executor application, position resort, and the post-execution invariant.
 *
 * Women's pyramid (config/countries.php): Liga F (ESP1, 16 teams, league),
 * Primera Federación (ESP2, 14 teams, 1 direct + 4-team playoff),
 * Segunda Federación (ESP3A/ESP3B/ESP3C, 14 teams each: group champions
 * promoted directly plus 1 ESP3PO playoff winner — split-format branch
 * with playoff_count = 1; when the playoff was never played the planner
 * promotes a standings stand-in instead). Relegation:
 * ESP1 drops positions [15,16], ESP2 drops [11,12,13,14] (four down
 * because four come up — exact tier sizes are enforced).
 */
class PromotionRelegationProcessorTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP1', 'tier' => 1, 'handler_type' => 'league']);
        Competition::factory()->league()->create(['id' => 'ESP2', 'tier' => 2, 'handler_type' => 'league_with_playoff']);
        Competition::factory()->league()->create(['id' => 'ESP3A', 'tier' => 3, 'handler_type' => 'league']);
        Competition::factory()->league()->create(['id' => 'ESP3B', 'tier' => 3, 'handler_type' => 'league']);
        Competition::factory()->league()->create(['id' => 'ESP3C', 'tier' => 3, 'handler_type' => 'league']);
        // The ESP3PO playoff bracket lives in its own competition id; the
        // cup_ties FK requires the row to exist when tests seed playoff ties.
        Competition::factory()->knockoutCup()->create(['id' => 'ESP3PO', 'name' => 'Playoff Ascenso Segunda Federación']);

        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP2',
            'season' => '2025',
            'country' => 'ES',
        ]);
    }

    /**
     * Production bug fix: when a parent club is being relegated from ESP1 into
     * ESP2 in the same season that its reserve is already in ESP2, the planner
     * cascades the reserve down to ESP3 to maintain the strict parent-above-
     * reserve invariant. Before the rewrite this produced "Reserve/parent
     * coexistence invariant violated" in production and required a manual
     * artisan repair.
     */
    public function test_parent_relegating_into_reserve_tier_cascades_reserve_to_esp3(): void
    {
        // ESP1: 16 teams, parent at position 15 (relegating)
        $parent = null;
        for ($i = 1; $i <= 16; $i++) {
            $team = Team::factory()->create(['country' => 'ES']);
            if ($i === 15) {
                $parent = $team;
            }
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $team->id, 'entry_round' => 1]);
            GameStanding::create([
                'game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $team->id,
                'position' => $i, 'played' => 30, 'won' => max(0, 25 - $i), 'drawn' => 5, 'lost' => $i,
                'goals_for' => 60 - $i, 'goals_against' => 20 + $i, 'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }

        // ESP2: 14 teams. Reserve of $parent is at position 10. Player's team at position 11.
        $reserve = Team::factory()->create(['country' => 'ES', 'parent_team_id' => $parent->id]);
        for ($i = 1; $i <= 14; $i++) {
            if ($i === 10) {
                $team = $reserve;
            } elseif ($i === 11) {
                $team = $this->game->team; // user's team
            } else {
                $team = Team::factory()->create(['country' => 'ES']);
            }
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $team->id, 'entry_round' => 1]);
            GameStanding::create([
                'game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $team->id,
                'position' => $i, 'played' => 26, 'won' => max(0, 25 - $i), 'drawn' => 5, 'lost' => $i,
                'goals_for' => 70 - $i, 'goals_against' => 20 + $i, 'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }

        // ESP3A + ESP3B + ESP3C: simulated (player isn't in them)
        $this->seedSimulatedTier('ESP3A', 14);
        $this->seedSimulatedTier('ESP3B', 14);
        $this->seedSimulatedTier('ESP3C', 14);

        // Run the processor
        $processor = app(PromotionRelegationProcessor::class);
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        ));

        // Parent relegated to ESP2
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'ESP2')
                ->where('team_id', $parent->id)
                ->exists(),
            'Parent should now be in ESP2',
        );

        // Reserve cascaded out of ESP2
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'ESP2')
                ->where('team_id', $reserve->id)
                ->exists(),
            'Reserve should no longer be in ESP2',
        );

        // Reserve now in ESP3A, ESP3B or ESP3C
        $reserveDestination = CompetitionEntry::where('game_id', $this->game->id)
            ->where('team_id', $reserve->id)
            ->whereIn('competition_id', ['ESP3A', 'ESP3B', 'ESP3C'])
            ->value('competition_id');
        $this->assertNotNull($reserveDestination, 'Reserve should be in one of ESP3A/ESP3B/ESP3C');

        // Tier sizes preserved
        $this->assertSame(16, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP1')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP2')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3A')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3B')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3C')->count());
    }

    /**
     * Two-pass / no-cascading-relegation check: when the player is in ESP2
     * (real standings) and ESP1, ESP3A, ESP3B, ESP3C are simulated, the rule
     * that relegates ESP1 teams into ESP2 must NOT cause those teams to then
     * be relegated again into ESP3 by the ESP2↔ESP3 rule. The planner reads
     * all standings before applying any moves, so relegated ESP1 teams aren't
     * visible in ESP2's relegation slot at read time.
     */
    public function test_relegated_esp1_teams_do_not_cascade_to_esp3(): void
    {
        $esp1TeamIds = [];
        for ($i = 0; $i < 16; $i++) {
            $team = Team::factory()->create(['country' => 'ES']);
            $esp1TeamIds[] = $team->id;
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $team->id, 'entry_round' => 1]);
        }
        SimulatedSeason::create([
            'game_id' => $this->game->id, 'season' => '2025', 'competition_id' => 'ESP1', 'results' => $esp1TeamIds,
        ]);

        $esp2TeamIds = [];
        for ($i = 1; $i <= 14; $i++) {
            if ($i === 11) {
                $team = $this->game->team;
            } else {
                $team = Team::factory()->create(['country' => 'ES']);
            }
            $esp2TeamIds[] = $team->id;
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $team->id, 'entry_round' => 1]);
            GameStanding::create([
                'game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $team->id,
                'position' => $i, 'played' => 26, 'won' => max(0, 25 - $i), 'drawn' => 5, 'lost' => $i,
                'goals_for' => 70 - $i, 'goals_against' => 20 + $i, 'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }

        $this->seedSimulatedTier('ESP3A', 14);
        $this->seedSimulatedTier('ESP3B', 14);
        $this->seedSimulatedTier('ESP3C', 14);

        $expectedRelegatedFromEsp1 = array_slice($esp1TeamIds, 14, 2);

        $processor = app(PromotionRelegationProcessor::class);
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        ));

        foreach ($expectedRelegatedFromEsp1 as $teamId) {
            $this->assertTrue(
                CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP2')->where('team_id', $teamId)->exists(),
                "ESP1-relegated team should land in ESP2",
            );
            $this->assertFalse(
                CompetitionEntry::where('game_id', $this->game->id)->whereIn('competition_id', ['ESP3A', 'ESP3B', 'ESP3C'])->where('team_id', $teamId)->exists(),
                "ESP1-relegated team must not cascade to ESP3",
            );
        }
    }

    /**
     * Reserve in ESP2 at position 1 (would direct-promote) while parent is mid-
     * table in ESP1 (not relegating). Reserve must be blocked and the next
     * non-reserve gets promoted.
     */
    public function test_reserve_at_top_of_esp2_is_filtered_when_parent_is_in_esp1(): void
    {
        $parent = Team::factory()->create(['country' => 'ES']);
        CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $parent->id, 'entry_round' => 1]);
        GameStanding::create([
            'game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $parent->id,
            'position' => 5, 'played' => 30, 'won' => 18, 'drawn' => 6, 'lost' => 14,
            'goals_for' => 50, 'goals_against' => 40, 'points' => 60,
        ]);
        // Pad ESP1 to 16 teams
        for ($i = 1; $i <= 16; $i++) {
            if ($i === 5) {
                continue;
            }
            $team = Team::factory()->create(['country' => 'ES']);
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $team->id, 'entry_round' => 1]);
            GameStanding::create([
                'game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $team->id,
                'position' => $i, 'played' => 30, 'won' => max(0, 25 - $i), 'drawn' => 5, 'lost' => $i,
                'goals_for' => 60 - $i, 'goals_against' => 20 + $i, 'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }

        $reserve = Team::factory()->create(['country' => 'ES', 'parent_team_id' => $parent->id]);
        CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $reserve->id, 'entry_round' => 1]);
        GameStanding::create([
            'game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $reserve->id,
            'position' => 1, 'played' => 26, 'won' => 28, 'drawn' => 8, 'lost' => 6,
            'goals_for' => 90, 'goals_against' => 30, 'points' => 92,
        ]);
        $esp2RunnerUp = null;
        for ($i = 2; $i <= 14; $i++) {
            if ($i === 11) {
                $team = $this->game->team;
            } else {
                $team = Team::factory()->create(['country' => 'ES']);
            }
            if ($i === 2) {
                $esp2RunnerUp = $team;
            }
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $team->id, 'entry_round' => 1]);
            GameStanding::create([
                'game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $team->id,
                'position' => $i, 'played' => 26, 'won' => max(0, 25 - $i), 'drawn' => 5, 'lost' => $i,
                'goals_for' => 70 - $i, 'goals_against' => 20 + $i, 'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }

        $this->seedSimulatedTier('ESP3A', 14);
        $this->seedSimulatedTier('ESP3B', 14);
        $this->seedSimulatedTier('ESP3C', 14);

        $processor = app(PromotionRelegationProcessor::class);
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        ));

        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP1')->where('team_id', $reserve->id)->exists(),
            'Reserve must not be promoted to ESP1 — parent is there',
        );

        // Position 2 takes the single direct-promotion slot instead.
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP1')->where('team_id', $esp2RunnerUp->id)->exists(),
            'ESP2 position 2 should be promoted directly',
        );
    }

    private function seedSimulatedTier(string $competitionId, int $count): void
    {
        $teamIds = [];
        for ($i = 0; $i < $count; $i++) {
            $team = Team::factory()->create(['country' => 'ES']);
            $teamIds[] = $team->id;
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => $competitionId, 'team_id' => $team->id, 'entry_round' => 1]);
        }
        SimulatedSeason::create([
            'game_id' => $this->game->id, 'season' => '2025', 'competition_id' => $competitionId, 'results' => $teamIds,
        ]);
    }

    /**
     * Segunda Federación promotion under the current design (ESP3PO
     * playoff, wired since 0ad5f19): the champion of each of the three
     * groups (ESP3A/ESP3B/ESP3C) is promoted directly to ESP2, and a
     * FOURTH team comes up through the ESP3PO bracket (3 runners-up +
     * best third, single final → 1 winner).
     *
     * When the playoff was never played (PlayoffState::NotStarted), the
     * planner's documented fallback promotes the next eligible team in
     * source-group order — the ESP3A runner-up — instead of leaving the
     * slot empty.
     */
    public function test_esp3_champions_are_promoted_directly_to_esp2(): void
    {
        $this->seedSimulatedTier('ESP1', 16);
        $this->seedRealTier('ESP2', 14, userPosition: 11);
        $esp3a = $this->seedRealTier('ESP3A', 14);
        $esp3b = $this->seedRealTier('ESP3B', 14);
        $esp3c = $this->seedRealTier('ESP3C', 14);

        $processor = app(PromotionRelegationProcessor::class);
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        ));

        // All three group champions go straight up.
        foreach ([$esp3a[1], $esp3b[1], $esp3c[1]] as $team) {
            $this->assertTrue(
                CompetitionEntry::where('game_id', $this->game->id)
                    ->where('competition_id', 'ESP2')
                    ->where('team_id', $team->id)
                    ->exists(),
                "ESP3 champion {$team->id} should be promoted directly to ESP2",
            );
        }

        // Playoff never played → stand-in fallback: the ESP3A runner-up
        // takes the fourth slot (sources are walked in ESP3A/ESP3B/ESP3C
        // order, skipping the direct-promotion slots).
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'ESP2')
                ->where('team_id', $esp3a[2]->id)
                ->exists(),
            'ESP3A runner-up should take the unplayed-playoff stand-in slot',
        );

        // The other runners-up stay down: only one stand-in slot exists.
        foreach ([$esp3b[2], $esp3c[2]] as $team) {
            $this->assertFalse(
                CompetitionEntry::where('game_id', $this->game->id)
                    ->where('competition_id', 'ESP2')
                    ->where('team_id', $team->id)
                    ->exists(),
                "ESP3 runner-up {$team->id} must remain in ESP3",
            );
        }

        // ESP3A lost two teams (champion + stand-in) and received two
        // relegated sides: tier sizes are preserved everywhere.
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP2')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3A')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3B')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3C')->count());
    }

    /**
     * When the ESP3PO final WAS played, its winner — not a standings
     * stand-in — takes the fourth promotion slot to ESP2. Uses a winner
     * that is neither a group champion nor the natural stand-in, so a
     * planner that ignored CupTie winners would fail this test.
     */
    public function test_esp3po_winner_takes_fourth_promotion_slot(): void
    {
        $this->seedSimulatedTier('ESP1', 16);
        $this->seedRealTier('ESP2', 14, userPosition: 11);
        $esp3a = $this->seedRealTier('ESP3A', 14);
        $esp3b = $this->seedRealTier('ESP3B', 14);
        $esp3c = $this->seedRealTier('ESP3C', 14);

        // ESP3PO final (round 2): ESP3C runner-up beats ESP3B third.
        CupTie::factory()->forGame($this->game)->inRound(2)
            ->between($esp3b[3], $esp3c[2])
            ->completed($esp3c[2], 'aggregate')
            ->create(['competition_id' => 'ESP3PO', 'bracket_position' => 1]);

        $processor = app(PromotionRelegationProcessor::class);
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        ));

        // Champions plus the playoff winner go up.
        foreach ([$esp3a[1], $esp3b[1], $esp3c[1], $esp3c[2]] as $team) {
            $this->assertTrue(
                CompetitionEntry::where('game_id', $this->game->id)
                    ->where('competition_id', 'ESP2')
                    ->where('team_id', $team->id)
                    ->exists(),
                "ESP3 team {$team->id} should be promoted to ESP2",
            );
        }

        // The natural stand-in (ESP3A runner-up) stays down when the
        // playoff produced a real winner.
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'ESP2')
                ->where('team_id', $esp3a[2]->id)
                ->exists(),
            'ESP3A runner-up must not take the slot when ESP3PO was played',
        );
    }

    /**
     * Locks in Rule 1: exactly two teams (positions 15-16) relegate from
     * Liga F to Primera Federación at season close.
     */
    public function test_esp1_bottom_two_are_relegated_to_esp2(): void
    {
        $esp1 = $this->seedRealTier('ESP1', 16);
        $this->seedRealTier('ESP2', 14, userPosition: 11);
        $this->seedSimulatedTier('ESP3A', 14);
        $this->seedSimulatedTier('ESP3B', 14);
        $this->seedSimulatedTier('ESP3C', 14);

        $relegated = [$esp1[15], $esp1[16]];
        $stayed = [$esp1[1], $esp1[14]];

        $processor = app(PromotionRelegationProcessor::class);
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        ));

        foreach ($relegated as $team) {
            $this->assertTrue(
                CompetitionEntry::where('game_id', $this->game->id)
                    ->where('competition_id', 'ESP2')
                    ->where('team_id', $team->id)
                    ->exists(),
                "ESP1 bottom-two team {$team->id} should land in ESP2",
            );
        }
        foreach ($stayed as $team) {
            $this->assertTrue(
                CompetitionEntry::where('game_id', $this->game->id)
                    ->where('competition_id', 'ESP1')
                    ->where('team_id', $team->id)
                    ->exists(),
                "ESP1 mid-table team {$team->id} should remain in ESP1",
            );
        }

        $this->assertSame(16, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP1')->count());
    }

    /**
     * Locks in Rule 2: exactly four teams (positions 11-14) relegate from
     * Primera Federación to Segunda Federación, distributed across
     * ESP3A/ESP3B/ESP3C. Four go down because four come up (3 champions +
     * 1 ESP3PO winner/stand-in) — the planner enforces exact tier sizes.
     */
    public function test_esp2_bottom_four_are_relegated_to_esp3(): void
    {
        $this->seedRealTier('ESP1', 16);
        $esp2 = $this->seedRealTier('ESP2', 14, userPosition: 11);
        $this->seedSimulatedTier('ESP3A', 14);
        $this->seedSimulatedTier('ESP3B', 14);
        $this->seedSimulatedTier('ESP3C', 14);

        $relegated = [$esp2[11], $esp2[12], $esp2[13], $esp2[14]];

        $processor = app(PromotionRelegationProcessor::class);
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        ));

        foreach ($relegated as $team) {
            $landedIn = CompetitionEntry::where('game_id', $this->game->id)
                ->whereIn('competition_id', ['ESP3A', 'ESP3B', 'ESP3C'])
                ->where('team_id', $team->id)
                ->value('competition_id');
            $this->assertNotNull($landedIn, "ESP2 bottom-four team {$team->id} should land in ESP3A, ESP3B or ESP3C");
        }

        // Position 10 stays in ESP2.
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'ESP2')
                ->where('team_id', $esp2[10]->id)
                ->exists(),
            'ESP2 position 10 should remain in ESP2',
        );

        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP2')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3A')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3B')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3C')->count());
    }

    /**
     * End-to-end invariant covering the women's Spanish cycle: after a full
     * season closing, exactly 2 teams move into ESP1 (1 direct from ESP2 +
     * 1 ESP2 playoff winner), exactly 4 teams move into ESP2 (the three
     * ESP3 group champions + the ESP3PO playoff winner), and tier sizes are
     * preserved.
     */
    public function test_full_spanish_cycle_promotes_2_to_esp1_and_4_to_esp2(): void
    {
        $esp1 = $this->seedRealTier('ESP1', 16);
        $esp2 = $this->seedRealTier('ESP2', 14, userPosition: 11);
        $esp3a = $this->seedRealTier('ESP3A', 14);
        $esp3b = $this->seedRealTier('ESP3B', 14);
        $esp3c = $this->seedRealTier('ESP3C', 14);

        // ESP2 playoff final (round 2): ESP2 pos 4 beats pos 5. Choosing the
        // lower seed (not the natural stand-in) catches a bug where the
        // planner would ignore CupTie winners and just take the next
        // available standings position.
        CupTie::factory()->forGame($this->game)->inRound(2)
            ->between($esp2[5], $esp2[4])
            ->completed($esp2[4], 'aggregate')
            ->create(['competition_id' => 'ESP2', 'bracket_position' => 1]);

        // ESP3PO final (round 2): ESP3B runner-up beats ESP3C third. The
        // winner is the fourth team promoted to ESP2.
        CupTie::factory()->forGame($this->game)->inRound(2)
            ->between($esp3c[3], $esp3b[2])
            ->completed($esp3b[2], 'aggregate')
            ->create(['competition_id' => 'ESP3PO', 'bracket_position' => 1]);

        $processor = app(PromotionRelegationProcessor::class);
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        ));

        // Promoted to ESP1: ESP2 pos 1 (direct) and pos 4 (playoff winner).
        foreach ([$esp2[1], $esp2[4]] as $team) {
            $this->assertTrue(
                CompetitionEntry::where('game_id', $this->game->id)
                    ->where('competition_id', 'ESP1')
                    ->where('team_id', $team->id)
                    ->exists(),
                "ESP2 team {$team->id} should be promoted to ESP1",
            );
        }

        // ESP2 pos 2, 3, 5 stayed (in the playoff bracket but didn't win —
        // confirms the planner respected the CupTie winner over standings).
        foreach ([$esp2[2], $esp2[3], $esp2[5]] as $team) {
            $this->assertFalse(
                CompetitionEntry::where('game_id', $this->game->id)
                    ->where('competition_id', 'ESP1')
                    ->where('team_id', $team->id)
                    ->exists(),
                "ESP2 playoff loser {$team->id} should NOT be promoted",
            );
        }

        // Promoted to ESP2: the three ESP3 group champions, directly, plus
        // the ESP3PO playoff winner (the fourth slot).
        foreach ([$esp3a[1], $esp3b[1], $esp3c[1], $esp3b[2]] as $team) {
            $this->assertTrue(
                CompetitionEntry::where('game_id', $this->game->id)
                    ->where('competition_id', 'ESP2')
                    ->where('team_id', $team->id)
                    ->exists(),
                "Segunda Federación team {$team->id} should be promoted to ESP2",
            );
        }

        // Relegated from ESP1: positions 15-16.
        foreach ([$esp1[15], $esp1[16]] as $team) {
            $this->assertTrue(
                CompetitionEntry::where('game_id', $this->game->id)
                    ->where('competition_id', 'ESP2')
                    ->where('team_id', $team->id)
                    ->exists(),
                "ESP1 team {$team->id} should be relegated to ESP2",
            );
        }

        // Relegated from ESP2: positions 11-14 land in ESP3A, ESP3B or ESP3C
        // (four down to balance the four promoted from ESP3).
        foreach ([$esp2[11], $esp2[12], $esp2[13], $esp2[14]] as $team) {
            $landed = CompetitionEntry::where('game_id', $this->game->id)
                ->whereIn('competition_id', ['ESP3A', 'ESP3B', 'ESP3C'])
                ->where('team_id', $team->id)
                ->exists();
            $this->assertTrue($landed, "ESP2 team {$team->id} should be relegated to Segunda Federación");
        }

        // Tier sizes preserved.
        $this->assertSame(16, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP1')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP2')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3A')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3B')->count());
        $this->assertSame(14, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3C')->count());
    }

    /**
     * @return array<int, Team> 1-indexed by standings position.
     */
    private function seedRealTier(string $competitionId, int $count, ?int $userPosition = null): array
    {
        $teams = [];
        for ($i = 1; $i <= $count; $i++) {
            $team = ($i === $userPosition)
                ? $this->game->team
                : Team::factory()->create(['country' => 'ES']);
            $teams[$i] = $team;
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'position' => $i,
                'played' => ($count - 1) * 2,
                'won' => max(0, 25 - $i),
                'drawn' => 5,
                'lost' => $i,
                'goals_for' => max(10, 70 - $i),
                'goals_against' => 20 + $i,
                'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }
        return $teams;
    }
}
