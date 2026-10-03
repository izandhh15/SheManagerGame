<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\CupDrawService;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Listeners\ConductNextCupRoundDraw;
use App\Modules\Match\Listeners\RouteQualifyingResultsListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Full pure-knockout Europa Cup flow (real UWEC 2026-27 format):
 *
 * UELQ round 1: 24 entries (entry_round=1) -> draw -> 12 ties -> 12 winners.
 * UELQ round 2: 12 winners + 20 entrants (entry_round=2: 8 UCLQ losers +
 *   12 direct league slots) -> draw -> 16 ties -> 16 winners.
 * UEL round of 16: the 16 winners -> draw -> 8 ties.
 * QF -> SF -> F -> champion, with the real listeners
 * (RouteQualifyingResultsListener + ConductNextCupRoundDraw) driving every
 * automatic draw. No draw may throw.
 */
class UelPureKnockoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Competition $uelq;
    private Competition $uel;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->create(['id' => 'UCLQ', 'handler_type' => 'knockout_cup']);
        Competition::factory()->create(['id' => 'UCL', 'handler_type' => 'swiss_format']);
        $this->uelq = Competition::factory()->create(['id' => 'UELQ', 'handler_type' => 'knockout_cup']);
        // Final phase is pure knockout: knockout_cup so the generic
        // ConductNextCupRoundDraw listener keeps drawing rounds for it.
        $this->uel = Competition::factory()->create(['id' => 'UEL', 'handler_type' => 'knockout_cup']);

        $userTeam = Team::factory()->create(['country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'team_id' => $userTeam->id,
            'season' => '2026',
            'base_season' => '2026',
        ]);
    }

    /** Create $count teams and register them as entries of a competition. */
    private function addEntries(string $competitionId, int $count, int $entryRound): array
    {
        $teams = Team::factory()->count($count)->create(['country' => 'ES']);

        foreach ($teams as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'entry_round' => $entryRound,
            ]);
        }

        return $teams->pluck('id')->all();
    }

    private function ties(string $competitionId, int $round): \Illuminate\Support\Collection
    {
        return CupTie::where('game_id', $this->game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', $round)
            ->orderBy('bracket_position')
            ->get();
    }

    /**
     * Mark a tie complete (home team wins by default) and run it through the
     * same listeners the real match-finalization flow fires.
     */
    private function resolveTie(CupTie $tie, ?string $winnerId = null): string
    {
        $winnerId ??= (string) $tie->home_team_id;

        $tie->update(['winner_id' => $winnerId, 'completed' => true]);

        $match = GameMatch::find($tie->second_leg_match_id)
            ?? GameMatch::find($tie->first_leg_match_id);

        $competition = $tie->competition_id === 'UEL' ? $this->uel : $this->uelq;

        $event = new CupTieResolved(
            cupTie: $tie->fresh(),
            winnerId: $winnerId,
            match: $match,
            game: $this->game,
            competition: $competition,
        );

        // Real flow order: routing first, then the next-round draw.
        app(RouteQualifyingResultsListener::class)->handle($event);
        app(ConductNextCupRoundDraw::class)->handle($event);

        return $winnerId;
    }

    public function test_uelq_qualifying_flow_produces_16_europa_cup_entrants(): void
    {
        // --- Round 1: 24 entrants -> 12 ties --------------------------------
        $this->addEntries('UELQ', 24, 1);

        $r1 = app(CupDrawService::class)->conductDraw($this->game->id, 'UELQ', 1);
        $this->assertCount(12, $r1, 'UELQ round 1 must draw 12 ties from 24 entrants');

        // Every entrant plays exactly one tie, no team drawn against itself.
        $r1Teams = $r1->flatMap(fn (CupTie $t) => [$t->home_team_id, $t->away_team_id]);
        $this->assertCount(24, $r1Teams->unique());
        foreach ($r1 as $tie) {
            $this->assertNotSame($tie->home_team_id, $tie->away_team_id);
        }

        // --- Round 2 field: 8 UCLQ losers drop in + 12 direct league slots --
        $r2Entrants = $this->addEntries('UELQ', 20, 2);
        $this->assertCount(20, $r2Entrants);

        // --- Complete round 1 through the real listeners --------------------
        $r1Winners = [];
        foreach ($this->ties('UELQ', 1) as $tie) {
            $r1Winners[] = $this->resolveTie($tie);
        }
        $this->assertCount(12, array_unique($r1Winners));

        // The listener drew round 2 automatically once round 1 was decided.
        $r2 = $this->ties('UELQ', 2);
        $this->assertCount(16, $r2, 'UELQ round 2 must draw 16 ties from 12 winners + 20 entrants');

        $r2Teams = $r2->flatMap(fn (CupTie $t) => [$t->home_team_id, $t->away_team_id]);
        $this->assertCount(32, $r2Teams->unique(), 'round 2 must involve 32 distinct teams');
        foreach ($r1Winners as $winner) {
            $this->assertTrue(
                $r2Teams->contains($winner),
                "round-1 winner {$winner} must be in the round-2 draw pool"
            );
        }

        // --- Complete round 2 ------------------------------------------------
        foreach ($r2 as $tie) {
            $this->resolveTie($tie);
        }

        // All 16 round-2 winners reach the Europa Cup; UELQ is empty.
        $uelEntries = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UEL')
            ->pluck('team_id')
            ->all();
        $this->assertCount(16, $uelEntries);
        $this->assertSame(
            0,
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UELQ')
                ->count()
        );

        // The listener drew the Europa Cup round of 16 automatically.
        $r16 = $this->ties('UEL', 1);
        $this->assertCount(8, $r16, 'UEL round of 16 must draw 8 ties from the 16 qualifiers');
        $r16Teams = $r16->flatMap(fn (CupTie $t) => [$t->home_team_id, $t->away_team_id]);
        $this->assertEqualsCanonicalizing($uelEntries, $r16Teams->all());
    }

    public function test_uel_final_phase_draws_qf_sf_final_until_champion(): void
    {
        $entrants = $this->addEntries('UEL', 16, 1);

        $draw = app(CupDrawService::class);

        $r16 = $draw->conductDraw($this->game->id, 'UEL', 1);
        $this->assertCount(8, $r16);

        // Nothing decided yet: no further round is due.
        $this->assertNull($draw->getNextRoundNeedingDraw($this->game->id, 'UEL'));

        // --- Round of 16 -----------------------------------------------------
        $r16Ties = $this->ties('UEL', 1)->all();
        foreach (array_slice($r16Ties, 0, 7) as $tie) {
            $this->resolveTie($tie);
        }
        // One tie still open: the quarter-final draw must NOT be proposed yet.
        $this->assertNull(
            $draw->getNextRoundNeedingDraw($this->game->id, 'UEL'),
            'QF must not be drawn while an R16 tie is still open'
        );
        $this->resolveTie($r16Ties[7]);

        $qf = $this->ties('UEL', 2);
        $this->assertCount(4, $qf, 'QF must draw 4 ties from the 8 R16 winners');

        // --- Quarter-finals --------------------------------------------------
        foreach ($qf as $tie) {
            $this->resolveTie($tie);
        }
        $sf = $this->ties('UEL', 3);
        $this->assertCount(2, $sf, 'SF must draw 2 ties from the 4 QF winners');

        // --- Semi-finals -----------------------------------------------------
        foreach ($sf as $tie) {
            $this->resolveTie($tie);
        }
        $final = $this->ties('UEL', 4);
        $this->assertCount(1, $final, 'the final must be drawn from the 2 SF winners');

        // --- Final -----------------------------------------------------------
        $champion = $this->resolveTie($final->first());

        // No rounds left: nothing more to draw, and the champion is one of
        // the 16 teams that entered the final phase.
        $this->assertNull($draw->getNextRoundNeedingDraw($this->game->id, 'UEL'));
        $this->assertContains($champion, $entrants);
        $this->assertSame(
            $champion,
            CupTie::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('round_number', 4)
                ->value('winner_id')
        );
    }
}
