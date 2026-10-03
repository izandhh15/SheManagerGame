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
 * Regresión del bug medio M31 (QA agent-28).
 *
 * `SeasonArchiveProcessor::calculateAwards()` usaba
 * MIN_GOALKEEPER_APPEARANCES = 19 fijo ("50% de los partidos de liga",
 * válido solo para ligas de 38 jornadas): en ligas cortas archivaba
 * `best_goalkeeper: null` aunque la gala hubiera coronado una Zamora con
 * su listón proporcional (70% de los partidos). Ahora el archivo usa el
 * mismo listón proporcional que la gala (~70% de los partidos jugados en
 * la competición principal).
 */
class M31ArchiveZamoraProportionalBarTest extends TestCase
{
    use RefreshDatabase;

    protected function makeGame(int $played): array
    {
        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['name' => 'M31 Valencia']);
        $rivalTeam = Team::factory()->create(['name' => 'M31 Madrid']);
        $competition = Competition::factory()->league()->create();

        $game = Game::factory()->forTeam($userTeam)->create([
            'user_id' => $user->id,
            'competition_id' => $competition->id,
            'season' => '2025',
        ]);

        foreach ([$userTeam, $rivalTeam] as $team) {
            CompetitionEntry::create([
                'game_id' => $game->id,
                'competition_id' => $competition->id,
                'team_id' => $team->id,
            ]);
            GameStanding::create([
                'game_id' => $game->id,
                'competition_id' => $competition->id,
                'team_id' => $team->id,
                'position' => $team->id === $userTeam->id ? 1 : 2,
                'played' => $played,
                'won' => 5, 'drawn' => 3, 'lost' => 2,
                'goals_for' => 20, 'goals_against' => 10,
                'points' => 18,
            ]);
        }

        return [$game, $competition, $userTeam, $rivalTeam];
    }

    private function makeKeeper(Game $game, Team $team, string $name, array $stats): GamePlayer
    {
        return GamePlayer::factory()->create(array_merge([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'name' => $name,
            'position' => 'Goalkeeper',
        ], $stats));
    }

    private function runArchive(Game $game, Competition $competition): SeasonArchive
    {
        $processor = new SeasonArchiveProcessor;
        $processor->process($game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $competition->id,
        ));

        return SeasonArchive::where('game_id', $game->id)
            ->where('season', '2025')
            ->firstOrFail();
    }

    public function test_short_league_archives_zamora_with_proportional_bar(): void
    {
        // Liga de 10 partidos: listón proporcional = ceil(10 * 0.7) = 7.
        [$game, $competition, $userTeam, $rivalTeam] = $this->makeGame(10);

        $wall = $this->makeKeeper($game, $userTeam, 'M31 Muro', [
            'goals_conceded' => 4, 'appearances' => 8, 'clean_sheets' => 5,
        ]);
        $this->makeKeeper($game, $rivalTeam, 'M31 Coladero', [
            'goals_conceded' => 9, 'appearances' => 10, 'clean_sheets' => 2,
        ]);

        $archive = $this->runArchive($game, $competition);

        // Con el umbral viejo (19) esto era null; la gala sí coronaba.
        $zamora = $archive->season_awards['best_goalkeeper'];

        $this->assertNotNull($zamora);
        $this->assertSame($wall->id, $zamora['player_id']);
        $this->assertSame(8, $zamora['appearances']);
    }

    public function test_keeper_below_proportional_bar_is_excluded(): void
    {
        // Liga de 10 partidos: listón = 7; una suplente con 6 partidos
        // (4 encajados) no debe ganar aunque tenga mejor ratio.
        [$game, $competition, $userTeam, $rivalTeam] = $this->makeGame(10);

        $this->makeKeeper($game, $userTeam, 'M31 Suplente', [
            'goals_conceded' => 4, 'appearances' => 6, 'clean_sheets' => 4,
        ]);
        $regular = $this->makeKeeper($game, $rivalTeam, 'M31 Titular', [
            'goals_conceded' => 9, 'appearances' => 10, 'clean_sheets' => 2,
        ]);

        $archive = $this->runArchive($game, $competition);

        $this->assertSame($regular->id, $archive->season_awards['best_goalkeeper']['player_id']);
    }

    public function test_full_length_league_keeps_working(): void
    {
        // Liga de 38 partidos: listón = ceil(38 * 0.7) = 27.
        [$game, $competition, $userTeam, $rivalTeam] = $this->makeGame(38);

        $wall = $this->makeKeeper($game, $userTeam, 'M31 Muro Larga', [
            'goals_conceded' => 20, 'appearances' => 34, 'clean_sheets' => 16,
        ]);
        $this->makeKeeper($game, $rivalTeam, 'M31 Coladero Larga', [
            'goals_conceded' => 45, 'appearances' => 36, 'clean_sheets' => 8,
        ]);

        $archive = $this->runArchive($game, $competition);

        $this->assertSame($wall->id, $archive->season_awards['best_goalkeeper']['player_id']);
    }
}
