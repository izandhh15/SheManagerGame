<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Promotions\RepairOutcome;
use App\Modules\Competition\Promotions\ReserveParentCoexistenceRepairer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 7 de la revisión de medios (grupo 05): bottomTeamOf() elegía el
 * colista del tier superior sin comprobar filiales, así que el swap
 * COEXISTENCE podía introducir una NUEVA coexistencia (el colista, filial
 * de un equipo de la liga compartida, caía en la liga de su padre).
 * Ahora el plan se declara Unsafe en ese caso.
 */
class CoexistenceRepairerFixTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        Competition::factory()->league()->create(['id' => 'ESP2', 'country' => 'ES', 'tier' => 2]);

        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'ES',
            'team_id' => Team::factory()->create(['country' => 'ES'])->id,
            'competition_id' => 'ESP1',
            'season' => '2026',
            'current_date' => Carbon::parse('2027-06-01'),
        ]);
    }

    public function test_coexistence_swap_bails_when_bottom_team_is_reserve_linked(): void
    {
        // Liga compartida ESP2: el padre P y su filial R coexisten.
        $parent = Team::factory()->create(['country' => 'ES', 'name' => 'Parent FC']);
        $reserve = Team::factory()->create(['country' => 'ES', 'name' => 'Parent FC B', 'parent_team_id' => $parent->id]);
        $this->enter('ESP2', [$parent, $reserve]);

        // El colista de ESP1 (B) es filial de P2, que juega en ESP2: si B
        // cayera a ESP2, B y P2 coexistirían → nueva corrupción.
        $otherParent = Team::factory()->create(['country' => 'ES', 'name' => 'Other FC']);
        $bottom = Team::factory()->create(['country' => 'ES', 'name' => 'Bottom FC', 'parent_team_id' => $otherParent->id]);
        $this->enter('ESP2', [$otherParent]);
        $this->enter('ESP1', [
            Team::factory()->create(['country' => 'ES', 'name' => 'Top FC']),
            $bottom,
        ], bottomTeam: $bottom);

        $result = app(ReserveParentCoexistenceRepairer::class)->plan($this->game);

        $this->assertSame(RepairOutcome::Unsafe, $result->outcome);
        $this->assertStringContainsString('new coexistence', $result->reason);
    }

    public function test_coexistence_swap_proceeds_when_bottom_team_is_clean(): void
    {
        $parent = Team::factory()->create(['country' => 'ES', 'name' => 'Parent FC']);
        $reserve = Team::factory()->create(['country' => 'ES', 'name' => 'Parent FC B', 'parent_team_id' => $parent->id]);
        $this->enter('ESP2', [$parent, $reserve]);

        $bottom = Team::factory()->create(['country' => 'ES', 'name' => 'Bottom FC']);
        $this->enter('ESP1', [
            Team::factory()->create(['country' => 'ES', 'name' => 'Top FC']),
            $bottom,
        ], bottomTeam: $bottom);

        $result = app(ReserveParentCoexistenceRepairer::class)->plan($this->game);

        $this->assertSame(RepairOutcome::Repaired, $result->outcome);
        $swap = $result->mutations[0]['swap'];
        $this->assertSame($parent->id, $swap['teamA']);   // el padre sube
        $this->assertSame($bottom->id, $swap['teamB']);   // el colista baja
        $this->assertSame('ESP1', $swap['leagueB']);
        $this->assertSame('ESP2', $swap['leagueA']);
    }

    /** @param Team[] $teams */
    private function enter(string $competitionId, array $teams, ?Team $bottomTeam = null): void
    {
        $position = 1;
        foreach ($teams as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
            ]);
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                // El colista declarado va el último sea cual sea el orden.
                'position' => $bottomTeam && $team->id === $bottomTeam->id ? 99 : $position++,
                'played' => 10,
                'points' => 10,
            ]);
        }
    }
}
