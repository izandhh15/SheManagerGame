<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Player\Services\PlayerConditionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug medio M1 (QA agent-7):
 * las suplentes que entran de cambio recibían trato de "banquillo" en
 * moral y fitness: frustración de banquillo en vez de bonus de gol, bonus
 * de victoria a mitad, y recuperación de no convocada en vez del desgaste
 * del partido.
 *
 * Causa raíz: PlayerConditionService::batchUpdateAfterMatchday calculaba
 * $isInLineup solo con home_lineup/away_lineup (el once inicial). El fix
 * fusiona también las entradas por cambio, desde la columna `substitutions`
 * del partido y desde los eventos `substitution` del batch (la misma fuente
 * que MatchResultProcessor::bulkUpdateAppearances usa para las apariciones).
 *
 * Determinismo: con la local muy favorita (overall 80 vs 50) el término de
 * sobre/rendimiento esperado queda en 0, así que los rangos son cerrados:
 * con el fix la suplente goleadora gana +6..+12 de moral (bonus victoria
 * 4-8 + gol 2-4); con el bug ganaría como mucho +3 (victoria a mitad 2-4
 * menos frustración 1-2, sin bonus de gol).
 */
class M1SubstituteMoraleFitnessTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $home;
    private Team $away;
    private PlayerConditionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->home = Team::factory()->create(['name' => 'QAM1 Home WFC', 'country' => 'ES']);
        $this->away = Team::factory()->create(['name' => 'QAM1 Away WFC', 'country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $this->home->id,
        ]);
        $this->service = app(PlayerConditionService::class);
    }

    private function player(Team $team, string $position, int $morale = 80, int $overall = 70): GamePlayer
    {
        $p = GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $team->id,
            'position' => $position,
            'overall_score' => $overall,
        ]);
        $p->matchState->update(['morale' => $morale, 'fitness' => 90]);

        return $p;
    }

    private function eleven(Team $team, int $overall): array
    {
        $positions = ['Goalkeeper', 'Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back',
            'Central Midfield', 'Central Midfield', 'Attacking Midfield',
            'Centre-Forward', 'Right Winger', 'Left Winger'];

        return array_map(fn ($pos) => $this->player($team, $pos, 80, $overall), $positions);
    }

    public function test_substitute_who_scores_gets_goal_bonus_not_bench_frustration(): void
    {
        $homeXI = $this->eleven($this->home, 80);
        $awayXI = $this->eleven($this->away, 50); // muy inferior: término de expectativa ≈ 0
        $subIn = $this->player($this->home, 'Centre-Forward', 80, 70);

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->game->competition_id,
            'home_team_id' => $this->home->id,
            'away_team_id' => $this->away->id,
            'home_lineup' => array_map(fn ($p) => $p->id, $homeXI),
            'away_lineup' => array_map(fn ($p) => $p->id, $awayXI),
            'home_score' => 2,
            'away_score' => 0,
            'played' => true,
            'scheduled_date' => Carbon::parse('2024-08-19'),
        ]);

        $events = [
            [
                'game_player_id' => $homeXI[8]->id,
                'event_type' => 'substitution',
                'minute' => 60,
                'team_id' => $this->home->id,
                'metadata' => ['player_in_id' => $subIn->id],
            ],
            [
                'game_player_id' => $subIn->id,
                'event_type' => 'goal',
                'minute' => 75,
                'team_id' => $this->home->id,
                'metadata' => [],
            ],
        ];

        $homePlayers = GamePlayer::with('matchState')
            ->where('game_id', $this->game->id)->where('team_id', $this->home->id)->get();
        $awayPlayers = GamePlayer::with('matchState')
            ->where('game_id', $this->game->id)->where('team_id', $this->away->id)->get();

        $this->service->batchUpdateAfterMatchday(
            collect([$match]),
            [['matchId' => $match->id, 'events' => $events]],
            collect([$this->home->id => $homePlayers, $this->away->id => $awayPlayers]),
            [$this->home->id => 7, $this->away->id => 7],
            Carbon::parse('2024-08-20'),
        );

        $subMorale = (int) $subIn->refresh()->morale;

        // Bonus de victoria (4-8) + bonus de gol (2-4) = +6..+12 ⇒ moral ≥ 86.
        // Con el bug (trato de banquillo) el máximo posible era 83.
        $this->assertGreaterThanOrEqual(
            86,
            $subMorale,
            "BUG M1: la suplente que entró y marcó tiene moral {$subMorale}; debería recibir bonus de gol + victoria completa (≥86), no frustración de banquillo"
        );
    }

    public function test_substitute_gets_match_fatigue_not_bench_recovery(): void
    {
        $homeXI = $this->eleven($this->home, 80);
        $awayXI = $this->eleven($this->away, 50);
        $subIn = $this->player($this->home, 'Centre-Forward', 80, 70);
        $benched = $this->player($this->home, 'Central Midfield', 80, 70);

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->game->competition_id,
            'home_team_id' => $this->home->id,
            'away_team_id' => $this->away->id,
            'home_lineup' => array_map(fn ($p) => $p->id, $homeXI),
            'away_lineup' => array_map(fn ($p) => $p->id, $awayXI),
            'home_score' => 2,
            'away_score' => 0,
            'played' => true,
            'scheduled_date' => Carbon::parse('2024-08-19'),
        ]);

        $events = [
            [
                'game_player_id' => $homeXI[8]->id,
                'event_type' => 'substitution',
                'minute' => 60,
                'team_id' => $this->home->id,
                'metadata' => ['player_in_id' => $subIn->id],
            ],
        ];

        $homePlayers = GamePlayer::with('matchState')
            ->where('game_id', $this->game->id)->where('team_id', $this->home->id)->get();
        $awayPlayers = GamePlayer::with('matchState')
            ->where('game_id', $this->game->id)->where('team_id', $this->away->id)->get();

        // 1 día de recuperación (semana congestionada): el desgaste del partido
        // supera con creces la recuperación, así que quien jugó baja de 90.
        $this->service->batchUpdateAfterMatchday(
            collect([$match]),
            [['matchId' => $match->id, 'events' => $events]],
            collect([$this->home->id => $homePlayers, $this->away->id => $awayPlayers]),
            [$this->home->id => 1, $this->away->id => 1],
            Carbon::parse('2024-08-20'),
        );

        $subFitness = (int) $subIn->refresh()->fitness;
        $benchedFitness = (int) $benched->refresh()->fitness;

        $this->assertLessThan(
            90,
            $subFitness,
            "BUG M1: la suplente que jugó 30' tiene fitness {$subFitness}; debería acumular desgaste de partido (<90), no recuperación de banquillo"
        );
        $this->assertGreaterThanOrEqual(
            90,
            $benchedFitness,
            'La suplente NO utilizada sí debería recuperar (≥90), sin desgaste de partido'
        );
    }

    public function test_substitute_counted_from_persisted_substitutions_column(): void
    {
        $homeXI = $this->eleven($this->home, 80);
        $awayXI = $this->eleven($this->away, 50);
        $subIn = $this->player($this->home, 'Centre-Forward', 80, 70);

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->game->competition_id,
            'home_team_id' => $this->home->id,
            'away_team_id' => $this->away->id,
            'home_lineup' => array_map(fn ($p) => $p->id, $homeXI),
            'away_lineup' => array_map(fn ($p) => $p->id, $awayXI),
            'home_score' => 2,
            'away_score' => 0,
            'played' => true,
            'scheduled_date' => Carbon::parse('2024-08-19'),
            // Sin eventos en el batch: el cambio solo vive en la columna JSON
            // (ruta del partido diferido del usuario).
            'substitutions' => [[
                'team_id' => $this->home->id,
                'player_out_id' => $homeXI[8]->id,
                'player_in_id' => $subIn->id,
                'minute' => 60,
            ]],
        ]);

        $homePlayers = GamePlayer::with('matchState')
            ->where('game_id', $this->game->id)->where('team_id', $this->home->id)->get();
        $awayPlayers = GamePlayer::with('matchState')
            ->where('game_id', $this->game->id)->where('team_id', $this->away->id)->get();

        $this->service->batchUpdateAfterMatchday(
            collect([$match]),
            [['matchId' => $match->id, 'events' => []]],
            collect([$this->home->id => $homePlayers, $this->away->id => $awayPlayers]),
            [$this->home->id => 7, $this->away->id => 7],
            Carbon::parse('2024-08-20'),
        );

        $subMorale = (int) $subIn->refresh()->morale;

        // Bonus de victoria completo (4-8), sin frustración de banquillo ⇒ ≥ 84.
        // Con el bug: victoria a mitad (2-4) menos frustración (1-2) ⇒ ≤ 82.
        $this->assertGreaterThanOrEqual(
            84,
            $subMorale,
            "BUG M1: el cambio registrado en la columna substitutions no se fusionó al once (moral {$subMorale})"
        );
    }
}
