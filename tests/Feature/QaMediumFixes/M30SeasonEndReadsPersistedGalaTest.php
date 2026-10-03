<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\GameStanding;
use App\Models\SeasonAward;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\MediaOutletService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Report\Services\AwardService;
use App\Modules\Report\Services\SeasonSummaryService;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\AwardsGalaProcessor;
use App\Modules\Season\Services\AwardsGalaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug medio M30 (QA agent-28).
 *
 * Las filas de la gala persistidas en `season_awards` por
 * AwardsGalaProcessor no se consultaban en ningún sitio: la pantalla de
 * fin de temporada (`SeasonSummaryService::buildSeasonSummary`) recomputaba
 * los ganadores en vivo con `AwardsGalaService::computeWinners()`, de modo
 * que tras el reset de stats salía una gala "Frankenstein" en lugar de los
 * ganadores oficiales. Ahora el resumen lee primero el registro persistido
 * y solo recurre al recompute en vivo si no hay gala guardada (temporada
 * aún no cerrada).
 */
class M30SeasonEndReadsPersistedGalaTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Team $userTeam;
    protected Team $rivalTeam;
    protected Competition $competition;
    protected Game $game;

    protected GamePlayer $starStriker;
    protected GamePlayer $rivalStriker;
    protected GamePlayer $wallKeeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'M30 Valencia']);
        $this->rivalTeam = Team::factory()->create(['name' => 'M30 Madrid']);
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
                'played' => 10,
                'won' => 5, 'drawn' => 3, 'lost' => 2,
                'goals_for' => 20, 'goals_against' => 10,
                'points' => 18,
            ]);
        }

        $this->starStriker = $this->makePlayer($this->userTeam, 'M30 Estrella', [
            'position' => 'Striker', 'goals' => 25, 'assists' => 8,
            'appearances' => 30, 'clean_sheets' => 0,
        ]);
        $this->rivalStriker = $this->makePlayer($this->rivalTeam, 'M30 Goleadora', [
            'position' => 'Striker', 'goals' => 28, 'assists' => 5,
            'appearances' => 30, 'clean_sheets' => 0,
        ]);
        $this->wallKeeper = $this->makePlayer($this->userTeam, 'M30 Muro', [
            'position' => 'Goalkeeper', 'goals_conceded' => 12,
            'appearances' => 28, 'clean_sheets' => 14,
        ]);

        // Match-MVP awards: 4 for the star striker, 1 for the rival striker.
        foreach (range(1, 4) as $i) {
            $this->makePlayedMatch($this->starStriker);
        }
        $this->makePlayedMatch($this->rivalStriker);
    }

    private function makePlayer(Team $team, string $name, array $stats): GamePlayer
    {
        return GamePlayer::factory()->create(array_merge([
            'game_id' => $this->game->id,
            'team_id' => $team->id,
            'name' => $name,
        ], $stats));
    }

    private function makePlayedMatch(GamePlayer $mvp): void
    {
        GameMatch::factory()->forGame($this->game)->create([
            'competition_id' => $this->competition->id,
            'home_team_id' => $this->userTeam->id,
            'away_team_id' => $this->rivalTeam->id,
            'played' => true,
            'home_score' => 2,
            'away_score' => 1,
            'mvp_player_id' => $mvp->id,
        ]);
    }

    private function runGala(): void
    {
        $processor = new AwardsGalaProcessor(
            new AwardsGalaService(new AwardService),
            new NotificationService,
            new MediaOutletService,
        );

        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $this->competition->id,
        ));
    }

    /**
     * Simula el StatsResetProcessor: las stats de la temporada se borran,
     * así que un recompute en vivo ya no puede recuperar a las ganadoras.
     */
    private function wipeSeasonStats(): void
    {
        GamePlayerMatchState::where('game_id', $this->game->id)->update([
            'goals' => 0,
            'assists' => 0,
            'appearances' => 0,
            'clean_sheets' => 0,
            'goals_conceded' => 0,
        ]);
        GameMatch::where('game_id', $this->game->id)->delete();
    }

    private function galaWinners(): array
    {
        return $this->app->make(SeasonSummaryService::class)
            ->buildSeasonSummary($this->game->fresh())['galaWinners'];
    }

    public function test_season_end_reads_persisted_gala_after_stats_reset(): void
    {
        $this->runGala();
        $this->wipeSeasonStats();

        // Sin el fix, el recompute en vivo tras el reset devuelve una gala
        // vacía ("Frankenstein"); con el fix salen las 4 ganadoras oficiales.
        $gala = $this->galaWinners();

        $this->assertCount(4, $gala);
        $this->assertSame($this->rivalStriker->id, $gala['pichichi']['player']->id);
        $this->assertSame(28, $gala['pichichi']['detail']['goals']);
        $this->assertSame($this->wallKeeper->id, $gala['zamora']['player']->id);
        $this->assertSame($this->starStriker->id, $gala['ballon_dor']['player']->id);
        $this->assertSame($this->starStriker->id, $gala['mvp']['player']->id);
        $this->assertSame(4, $gala['mvp']['detail']['mvp_awards']);
    }

    public function test_season_end_falls_back_to_live_compute_before_close(): void
    {
        // Temporada aún no cerrada (sin filas persistidas): el resumen
        // sigue mostrando la gala calculada en vivo.
        $gala = $this->galaWinners();

        $this->assertNotEmpty($gala);
        $this->assertSame($this->rivalStriker->id, $gala['pichichi']['player']->id);
        $this->assertSame($this->wallKeeper->id, $gala['zamora']['player']->id);
    }

    public function test_persisted_gala_ignores_deleted_player_rows(): void
    {
        $this->runGala();

        // Si la fila de la jugadora desapareciera (p. ej. retirada con
        // borrado), ese premio se omite en vez de romper la pantalla.
        $this->rivalStriker->delete();

        $gala = $this->galaWinners();

        $this->assertArrayNotHasKey('pichichi', $gala);
        $this->assertArrayHasKey('zamora', $gala);
    }
}
