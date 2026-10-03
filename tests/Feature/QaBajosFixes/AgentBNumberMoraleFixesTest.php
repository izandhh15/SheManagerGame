<?php

namespace Tests\Feature\QaBajosFixes;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Player\Services\PlayerConditionService;
use App\Modules\Squad\Exceptions\NoSquadNumberAvailableException;
use App\Modules\Squad\Services\SquadNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA FASE 4 (bugs bajos) — Agente B.
 *
 * B4. SquadNumberService::assignNumberForNewPlayer: with 1-25 full and the
 * 26-99 academy full too, bumping a youngster used to null her number
 * (silent unenrollment, no user action). Now it throws
 * NoSquadNumberAvailableException and the youngster keeps her dorsal.
 *
 * B5. PlayerConditionService::calculateMoraleChange: long-term injured
 * players were treated as benched (bench frustration penalty) even though
 * calculateFitnessChange already has an injured carve-out. Injured players
 * are now exempt from the bench-frustration penalty, mirroring fitness.
 */
class AgentBNumberMoraleFixesTest extends TestCase
{
    use RefreshDatabase;

    // ---------------- B4: dorsales ----------------

    public function test_bump_with_full_academy_throws_instead_of_nulling_youngster(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'QAB Bump WFC', 'country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'season' => '2026',
        ]);

        $youngDob = Carbon::parse('2026-01-01')->subYears(19)->toDateString();
        $oldDob = Carbon::parse('2026-01-01')->subYears(30)->toDateString();

        // 1-25: 24 mayores + 1 joven (dorsal 7)
        for ($i = 1; $i <= 25; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $game->id,
                'team_id' => $team->id,
                'position' => 'Central Midfield',
                'number' => $i,
                'date_of_birth' => $i === 7 ? $youngDob : $oldDob,
            ]);
        }
        // 26-99 lleno de jóvenes
        for ($i = 26; $i <= 99; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $game->id,
                'team_id' => $team->id,
                'position' => 'Central Midfield',
                'number' => $i,
                'date_of_birth' => $youngDob,
            ]);
        }

        $youngster = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $team->id)->where('number', 7)->firstOrFail();

        // Fichaje de una mayor de 23
        $signing = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'position' => 'Centre-Forward',
            'number' => null,
            'date_of_birth' => $oldDob,
        ]);

        try {
            app(SquadNumberService::class)->assignNumberForNewPlayer($game, $signing);
            $this->fail('Expected NoSquadNumberAvailableException when 1-25 and 26-99 are both full');
        } catch (NoSquadNumberAvailableException $e) {
            $this->assertNotEmpty($e->getMessage(), 'La excepción controlada debe llevar mensaje');
        }

        $this->assertSame(
            7,
            (int) $youngster->refresh()->number,
            'La joven desplazada conserva su dorsal: el fichaje falla en voz alta en vez de anularla'
        );
    }

    public function test_bump_with_free_academy_slot_still_works(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'QAB BumpFree WFC', 'country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'season' => '2026',
        ]);

        $youngDob = Carbon::parse('2026-01-01')->subYears(19)->toDateString();
        $oldDob = Carbon::parse('2026-01-01')->subYears(30)->toDateString();

        for ($i = 1; $i <= 25; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $game->id,
                'team_id' => $team->id,
                'position' => 'Central Midfield',
                'number' => $i,
                'date_of_birth' => $i === 7 ? $youngDob : $oldDob,
            ]);
        }
        // Academia con hueco libre (26-98 ocupados)
        for ($i = 26; $i <= 98; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $game->id,
                'team_id' => $team->id,
                'position' => 'Central Midfield',
                'number' => $i,
                'date_of_birth' => $youngDob,
            ]);
        }

        $youngster = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $team->id)->where('number', 7)->firstOrFail();

        $signing = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'position' => 'Centre-Forward',
            'number' => null,
            'date_of_birth' => $oldDob,
        ]);

        $number = app(SquadNumberService::class)->assignNumberForNewPlayer($game, $signing);

        $this->assertSame(7, $number, 'El fichaje recibe el dorsal liberado');
        $this->assertSame(99, (int) $youngster->refresh()->number, 'La joven se desplaza a la cantera');
    }

    // ---------------- B5: moral de lesionadas ----------------

    private Game $game;
    private Team $home;
    private Team $away;
    private PlayerConditionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->home = Team::factory()->create(['name' => 'QAB Home WFC', 'country' => 'ES']);
        $this->away = Team::factory()->create(['name' => 'QAB Away WFC', 'country' => 'ES']);
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

    private function runBatch(GameMatch $match, array $events = []): void
    {
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
    }

    private function makeMatch(array $homeXI, array $awayXI, int $homeScore, int $awayScore): GameMatch
    {
        return GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->game->competition_id,
            'home_team_id' => $this->home->id,
            'away_team_id' => $this->away->id,
            'home_lineup' => array_map(fn ($p) => $p->id, $homeXI),
            'away_lineup' => array_map(fn ($p) => $p->id, $awayXI),
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'played' => true,
            'scheduled_date' => Carbon::parse('2024-08-19'),
        ]);
    }

    private function eleven(Team $team, int $morale = 80, int $overall = 70): array
    {
        $positions = ['Goalkeeper', 'Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back',
            'Central Midfield', 'Central Midfield', 'Attacking Midfield',
            'Centre-Forward', 'Right Winger', 'Left Winger'];

        return array_map(fn ($pos) => $this->player($team, $pos, $morale, $overall), $positions);
    }

    public function test_long_term_injured_player_is_exempt_from_bench_frustration(): void
    {
        $homeXI = $this->eleven($this->home, 80);
        $awayXI = $this->eleven($this->away, 80);

        $injured = $this->player($this->home, 'Central Midfield', 80);
        $injured->matchState->update([
            'injury_until' => Carbon::parse('2025-03-01'), // 6 meses de baja
            'injury_type' => 'ACL tear',
        ]);

        $match = $this->makeMatch($homeXI, $awayXI, 2, 2);

        $moraleDrop = 0;
        for ($i = 0; $i < 10; $i++) {
            $before = (int) $injured->refresh()->morale;
            $this->runBatch($match);
            $after = (int) $injured->refresh()->morale;
            $moraleDrop += max(0, $before - $after);
        }

        $this->assertSame(
            0,
            $moraleDrop,
            'La lesionada de larga duración ya NO pierde moral por frustración de banquillo'
        );
        $this->assertGreaterThanOrEqual(
            80,
            (int) $injured->refresh()->morale,
            'Su moral nunca baja por no poder jugar'
        );
    }

    public function test_healthy_bench_player_still_gets_bench_frustration(): void
    {
        $homeXI = $this->eleven($this->home, 80);
        $awayXI = $this->eleven($this->away, 80);

        // Estrella sana sin minutos: la frustración de banquillo sigue aplicándole
        $benchStar = $this->player($this->home, 'Central Midfield', 80, 100);

        $match = $this->makeMatch($homeXI, $awayXI, 2, 2);

        $moraleDrop = 0;
        for ($i = 0; $i < 10; $i++) {
            $before = (int) $benchStar->refresh()->morale;
            $this->runBatch($match);
            $after = (int) $benchStar->refresh()->morale;
            $moraleDrop += max(0, $before - $after);
        }

        $this->assertGreaterThan(
            0,
            $moraleDrop,
            'El carve-out de lesionadas no debe desactivar la frustración de banquillo para las sanas'
        );
    }
}
