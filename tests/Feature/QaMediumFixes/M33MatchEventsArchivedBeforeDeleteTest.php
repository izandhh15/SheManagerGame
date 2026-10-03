<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\MatchEvent;
use App\Models\SeasonArchive;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SeasonArchiveProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regresión del bug medio M33 (QA agent-28).
 *
 * Los `match_events` se borraban en el cierre de temporada sin archivarse:
 * se perdía quién marcó, las asistencias y las tarjetas de forma
 * permanente. Ahora `SeasonArchiveProcessor` (y el rollover de
 * selecciones) capturan los eventos en la columna
 * `season_archives.match_events_archive` ANTES del borrado.
 */
class M33MatchEventsArchivedBeforeDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Team $userTeam;
    protected Team $rivalTeam;
    protected Competition $competition;
    protected Game $game;
    protected GameMatch $match;
    protected GamePlayer $scorer;
    protected GamePlayer $assister;
    protected GamePlayer $booked;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'M33 Valencia']);
        $this->rivalTeam = Team::factory()->create(['name' => 'M33 Madrid']);
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

        $this->scorer = $this->makePlayer($this->userTeam, 'M33 Goleadora');
        $this->assister = $this->makePlayer($this->userTeam, 'M33 Asistente');
        $this->booked = $this->makePlayer($this->rivalTeam, 'M33 Amonestada');

        $this->match = GameMatch::factory()->forGame($this->game)->create([
            'competition_id' => $this->competition->id,
            'home_team_id' => $this->userTeam->id,
            'away_team_id' => $this->rivalTeam->id,
            'played' => true,
            'home_score' => 1,
            'away_score' => 0,
        ]);

        $this->makeEvent($this->scorer, 23, MatchEvent::TYPE_GOAL);
        $this->makeEvent($this->assister, 23, MatchEvent::TYPE_ASSIST);
        $this->makeEvent($this->booked, 67, MatchEvent::TYPE_YELLOW_CARD);
    }

    private function makePlayer(Team $team, string $name): GamePlayer
    {
        return GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'team_id' => $team->id,
            'name' => $name,
        ]);
    }

    private function makeEvent(GamePlayer $player, int $minute, string $type): void
    {
        MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $this->match->id,
            'game_player_id' => $player->id,
            'team_id' => $player->team_id,
            'minute' => $minute,
            'event_type' => $type,
        ]);
    }

    private function runArchive(): SeasonArchive
    {
        $processor = new SeasonArchiveProcessor;
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $this->competition->id,
        ));

        return SeasonArchive::where('game_id', $this->game->id)
            ->where('season', '2025')
            ->firstOrFail();
    }

    public function test_match_events_are_archived_before_deletion(): void
    {
        $this->assertTrue(Schema::hasColumn('season_archives', 'match_events_archive'));

        $archive = $this->runArchive();

        $events = $archive->match_events_archive;

        $this->assertIsArray($events);
        $this->assertCount(3, $events);

        $byType = collect($events)->keyBy('event_type');

        $this->assertSame('M33 Goleadora', $byType['goal']['player_name']);
        $this->assertSame(23, $byType['goal']['minute']);
        $this->assertSame($this->scorer->id, $byType['goal']['game_player_id']);
        $this->assertSame('first_half', $byType['goal']['phase']);

        $this->assertSame('M33 Asistente', $byType['assist']['player_name']);
        $this->assertSame('M33 Amonestada', $byType['yellow_card']['player_name']);
        $this->assertSame(67, $byType['yellow_card']['minute']);
    }

    public function test_match_events_are_still_purged_from_active_table(): void
    {
        $this->runArchive();

        $this->assertSame(
            0,
            MatchEvent::where('game_id', $this->game->id)->count()
        );
    }

    public function test_archive_is_idempotent_for_events(): void
    {
        $this->runArchive();
        $this->runArchive();

        $archive = SeasonArchive::where('game_id', $this->game->id)
            ->where('season', '2025')
            ->firstOrFail();

        $this->assertCount(3, $archive->match_events_archive);
    }
}
