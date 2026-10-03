<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Listeners\RouteQualifyingResultsListener;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 8 de la revisión de medios (grupo 07): el perdedor de la UELQ
 * conservaba su entry, contradiciendo el docblock ("UELQ loser is out") y
 * contaminando strongestUnqualifiedEuropeanTeams() (los eliminados seguían
 * contando como "clasificados"). Ahora la entry se borra.
 */
class QualifyingResultsListenerFixTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $winner;
    private Team $loser;
    private CupTie $tie;
    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->knockoutCup()->create(['id' => 'UELQ', 'country' => 'EU']);
        Competition::factory()->knockoutCup()->create(['id' => 'UEL', 'country' => 'EU']);

        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'EU',
            'team_id' => Team::factory()->create(['country' => 'EU'])->id,
            'competition_id' => 'UELQ',
            'current_date' => Carbon::parse('2026-08-01'),
        ]);

        $this->winner = Team::factory()->create(['country' => 'EU']);
        $this->loser = Team::factory()->create(['country' => 'EU']);

        foreach ([$this->winner, $this->loser] as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'UELQ',
                'team_id' => $team->id,
            ]);
        }

        $this->tie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'UELQ',
            'home_team_id' => $this->winner->id,
            'away_team_id' => $this->loser->id,
            'completed' => true,
            'winner_id' => $this->winner->id,
        ]);

        $this->match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'UELQ',
            'home_team_id' => $this->winner->id,
            'away_team_id' => $this->loser->id,
        ]);

        // Otra eliminatoria aún sin decidir: evita que el listener intente
        // sortear la R2 de la UELQ o la R16 de la UEL (no es objeto de este
        // test).
        CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => 'UELQ',
            'home_team_id' => Team::factory()->create(['country' => 'EU'])->id,
            'away_team_id' => Team::factory()->create(['country' => 'EU'])->id,
            'completed' => false,
        ]);
    }

    public function test_uelq_loser_loses_their_entry(): void
    {
        $this->handleTieResolved();

        // El ganador de la R1 se queda en la UELQ (su entry ya cubre la R2)...
        $this->assertTrue($this->hasEntry($this->winner->id, 'UELQ'));
        $this->assertFalse($this->hasEntry($this->winner->id, 'UEL'));

        // ...y el perdedor queda fuera: sin entry en UELQ ni en UEL.
        $this->assertFalse($this->hasEntry($this->loser->id, 'UELQ'), 'el perdedor conserva su entry UELQ');
        $this->assertFalse($this->hasEntry($this->loser->id, 'UEL'));
    }

    public function test_uelq_loser_no_longer_counts_as_qualified(): void
    {
        $this->handleTieResolved();

        // strongestUnqualifiedEuropeanTeams() considera "clasificado" a
        // quien tenga entry en UCL/UEL/UCLQ/UELQ: el perdedor ya no aparece.
        $qualified = CompetitionEntry::where('game_id', $this->game->id)
            ->whereIn('competition_id', ['UCL', 'UEL', 'UCLQ', 'UELQ'])
            ->pluck('team_id')
            ->all();

        $this->assertContains((string) $this->winner->id, $qualified);
        $this->assertNotContains((string) $this->loser->id, $qualified);
    }

    private function handleTieResolved(): void
    {
        $event = new CupTieResolved(
            cupTie: $this->tie,
            winnerId: (string) $this->winner->id,
            match: $this->match,
            game: $this->game,
            competition: Competition::find('UELQ'),
        );

        app(RouteQualifyingResultsListener::class)->handle($event);
    }

    private function hasEntry(string $teamId, string $competitionId): bool
    {
        return CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', $competitionId)
            ->where('team_id', $teamId)
            ->exists();
    }
}
