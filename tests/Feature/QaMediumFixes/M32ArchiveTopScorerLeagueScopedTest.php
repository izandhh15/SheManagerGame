<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\SeasonArchive;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SeasonArchiveProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug medio M32 (QA agent-28).
 *
 * El `top_scorer` del archivo de temporada no estaba limitado a la liga:
 * la query solo pedía `team_id` no nulo, así que una jugadora de un equipo
 * SIN CompetitionEntry (fuera de la competición) con más goles coronaba
 * como pichichi del archivo, contradiciendo a la gala oficial (limitada a
 * la liga). Ahora los tres premios individuales del archivo se limitan a
 * los equipos inscritos en la competición principal, como la gala.
 */
class M32ArchiveTopScorerLeagueScopedTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Team $userTeam;
    protected Team $rivalTeam;
    protected Team $outsiderTeam;
    protected Competition $competition;
    protected Game $game;

    protected GamePlayer $leagueStriker;
    protected GamePlayer $outsiderStriker;
    protected GamePlayer $leaguePlaymaker;
    protected GamePlayer $outsiderPlaymaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'M32 Valencia']);
        $this->rivalTeam = Team::factory()->create(['name' => 'M32 Madrid']);
        // Equipo sin CompetitionEntry: fuera de la liga del juego.
        $this->outsiderTeam = Team::factory()->create(['name' => 'M32 Outsider']);
        $this->competition = Competition::factory()->league()->create();

        $this->game = Game::factory()->forTeam($this->userTeam)->create([
            'user_id' => $this->user->id,
            'competition_id' => $this->competition->id,
            'season' => '2025',
        ]);

        foreach ([$this->userTeam, $this->rivalTeam] as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => $this->competition->id,
                'team_id' => $team->id,
            ]);
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $this->competition->id,
                'team_id' => $team->id,
                'position' => $team->id === $this->userTeam->id ? 1 : 2,
                'played' => 30,
                'won' => 15, 'drawn' => 8, 'lost' => 7,
                'goals_for' => 50, 'goals_against' => 30,
                'points' => 53,
            ]);
        }

        $this->leagueStriker = $this->makePlayer($this->userTeam, 'M32 Goleadora Liga', [
            'position' => 'Striker', 'goals' => 22, 'assists' => 3, 'appearances' => 30,
        ]);
        $this->outsiderStriker = $this->makePlayer($this->outsiderTeam, 'M32 Goleadora Outsider', [
            'position' => 'Striker', 'goals' => 99, 'assists' => 1, 'appearances' => 30,
        ]);
        $this->leaguePlaymaker = $this->makePlayer($this->rivalTeam, 'M32 Asistente Liga', [
            'position' => 'Central Midfield', 'goals' => 5, 'assists' => 14, 'appearances' => 30,
        ]);
        $this->outsiderPlaymaker = $this->makePlayer($this->outsiderTeam, 'M32 Asistente Outsider', [
            'position' => 'Central Midfield', 'goals' => 2, 'assists' => 40, 'appearances' => 30,
        ]);
    }

    private function makePlayer(Team $team, string $name, array $stats): GamePlayer
    {
        return GamePlayer::factory()->create(array_merge([
            'game_id' => $this->game->id,
            'team_id' => $team->id,
            'name' => $name,
        ], $stats));
    }

    private function archivedAwards(): array
    {
        $processor = new SeasonArchiveProcessor;
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $this->competition->id,
        ));

        return SeasonArchive::where('game_id', $this->game->id)
            ->where('season', '2025')
            ->firstOrFail()
            ->season_awards;
    }

    public function test_top_scorer_is_limited_to_the_league(): void
    {
        $awards = $this->archivedAwards();

        // Sin el fix, la outsider (99 goles) coronaba como pichichi.
        $this->assertSame($this->leagueStriker->id, $awards['top_scorer']['player_id']);
        $this->assertSame(22, $awards['top_scorer']['goals']);
    }

    public function test_most_assists_is_limited_to_the_league(): void
    {
        $awards = $this->archivedAwards();

        // El mismo defecto afectaba a most_assists: se limita igual.
        $this->assertSame($this->leaguePlaymaker->id, $awards['most_assists']['player_id']);
        $this->assertSame(14, $awards['most_assists']['assists']);
    }
}
