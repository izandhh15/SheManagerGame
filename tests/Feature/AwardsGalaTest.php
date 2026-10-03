<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\ManagerTrophy;
use App\Models\MatchEvent;
use App\Models\SeasonAward;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\MediaOutletService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Report\Services\AwardService;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\AwardsGalaProcessor;
use App\Modules\Season\Services\AwardsGalaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Small simulated season: two teams, a handful of players with season
 * stats in their match states and match-MVP awards on played matches.
 * The gala must pick the right winners from that real data.
 */
class AwardsGalaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pin to pgsql: the suite's migrations use postgres-only DDL, so this
     * test cannot run on sqlite regardless of the base TestCase setting.
     *
     * @var array<int, string>
     */
    protected $connectionsToTransact = ['pgsql'];

    protected User $user;
    protected Team $userTeam;
    protected Team $rivalTeam;
    protected Competition $competition;
    protected Game $game;

    protected GamePlayer $starStriker;   // user team: 25 goals, 8 assists, 4 match MVPs
    protected GamePlayer $rivalStriker;  // rival team: 28 goals (pichichi), 5 assists, 1 MVP
    protected GamePlayer $wallKeeper;    // user team keeper: 12 conceded in 28 apps
    protected GamePlayer $leakyKeeper;   // rival keeper: 30 conceded in 28 apps
    protected GamePlayer $backupKeeper;  // rival keeper: 2 conceded in 2 apps (below the bar)

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'Valencia Stars']);
        $this->rivalTeam = Team::factory()->create(['name' => 'Madrid Rivals']);
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

        $this->starStriker = $this->makePlayer($this->userTeam, 'Delantera Estrella', [
            'position' => 'Striker', 'goals' => 25, 'assists' => 8,
            'appearances' => 30, 'clean_sheets' => 0,
        ]);
        $this->rivalStriker = $this->makePlayer($this->rivalTeam, 'Rival Goleadora', [
            'position' => 'Striker', 'goals' => 28, 'assists' => 5,
            'appearances' => 30, 'clean_sheets' => 0,
        ]);
        $this->wallKeeper = $this->makePlayer($this->userTeam, 'Portera Muro', [
            'position' => 'Goalkeeper', 'goals_conceded' => 12,
            'appearances' => 28, 'clean_sheets' => 14,
        ]);
        $this->leakyKeeper = $this->makePlayer($this->rivalTeam, 'Portera Coladero', [
            'position' => 'Goalkeeper', 'goals_conceded' => 30,
            'appearances' => 28, 'clean_sheets' => 5,
        ]);
        $this->backupKeeper = $this->makePlayer($this->rivalTeam, 'Portera Suplente', [
            'position' => 'Goalkeeper', 'goals_conceded' => 2,
            'appearances' => 2, 'clean_sheets' => 2,
        ]);

        // Match-MVP awards: 4 for the star striker, 1 for the rival striker.
        // Goal events matching the documented season tallies (25 / 28): the
        // pichichi is counted from the league's match events (B26), and real
        // simulated matches always produce them.
        $matches = [];
        foreach (range(1, 4) as $i) {
            $matches[] = $this->makePlayedMatch($this->starStriker);
        }
        $matches[] = $this->makePlayedMatch($this->rivalStriker);

        foreach ($matches as $match) {
            $this->scoreGoals($match, $this->starStriker, 5); // 5 x 5 = 25
        }
        foreach ($matches as $i => $match) {
            $this->scoreGoals($match, $this->rivalStriker, [6, 6, 6, 5, 5][$i]); // = 28
        }
    }

    private function makePlayer(Team $team, string $name, array $stats): GamePlayer
    {
        return GamePlayer::factory()->create(array_merge([
            'game_id' => $this->game->id,
            'team_id' => $team->id,
            'name' => $name,
        ], $stats));
    }

    private function makePlayedMatch(GamePlayer $mvp): GameMatch
    {
        return GameMatch::factory()->forGame($this->game)->create([
            'competition_id' => $this->competition->id,
            'home_team_id' => $this->userTeam->id,
            'away_team_id' => $this->rivalTeam->id,
            'played' => true,
            'home_score' => 2,
            'away_score' => 1,
            'mvp_player_id' => $mvp->id,
        ]);
    }

    /**
     * Record $count goal events for a player in a match.
     */
    private function scoreGoals(GameMatch $match, GamePlayer $player, int $count): void
    {
        foreach (range(1, $count) as $i) {
            MatchEvent::create([
                'game_id' => $this->game->id,
                'game_match_id' => $match->id,
                'game_player_id' => $player->id,
                'team_id' => $player->team_id,
                'minute' => min($i * 10, 90),
                'event_type' => MatchEvent::TYPE_GOAL,
            ]);
        }
    }

    private function processor(): AwardsGalaProcessor
    {
        return new AwardsGalaProcessor(
            new AwardsGalaService(new AwardService),
            new NotificationService,
            new MediaOutletService,
        );
    }

    private function runGala(): SeasonTransitionData
    {
        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $this->competition->id,
        );

        return $this->processor()->process($this->game, $data);
    }

    public function test_gala_picks_pichichi_with_most_goals(): void
    {
        $this->runGala();

        $pichichi = SeasonAward::where('game_id', $this->game->id)
            ->where('award_key', SeasonAward::AWARD_PICHICHI)
            ->first();

        $this->assertNotNull($pichichi);
        $this->assertEquals($this->rivalStriker->id, $pichichi->game_player_id);
        $this->assertEquals(28, $pichichi->detail['goals']);
    }

    public function test_gala_picks_zamora_with_fewest_conceded_per_match(): void
    {
        $this->runGala();

        $zamora = SeasonAward::where('game_id', $this->game->id)
            ->where('award_key', SeasonAward::AWARD_ZAMORA)
            ->first();

        $this->assertNotNull($zamora);
        // 12 conceded in 28 apps (0.43/match) beats 30 in 28 (1.07/match);
        // the backup keeper (2 in 2) is excluded by the appearances bar.
        $this->assertEquals($this->wallKeeper->id, $zamora->game_player_id);
        $this->assertEqualsWithDelta(0.43, $zamora->detail['goals_conceded_per_match'], 0.01);
    }

    public function test_gala_picks_ballon_dor_and_mvp(): void
    {
        $this->runGala();

        $ballonDor = SeasonAward::where('game_id', $this->game->id)
            ->where('award_key', SeasonAward::AWARD_BALLON_DOR)
            ->first();
        $mvp = SeasonAward::where('game_id', $this->game->id)
            ->where('award_key', SeasonAward::AWARD_MVP)
            ->first();

        $this->assertNotNull($ballonDor);
        $this->assertNotNull($mvp);

        // Star striker: 25*3 + 8*2 + 4*4 = 107 beats rival's 28*3 + 5*2 + 1*4 = 98.
        $this->assertEquals($this->starStriker->id, $ballonDor->game_player_id);
        // Most match-MVP awards (4 vs 1).
        $this->assertEquals($this->starStriker->id, $mvp->game_player_id);
        $this->assertEquals(4, $mvp->detail['mvp_awards']);
    }

    public function test_gala_records_palmarés_trophies_only_for_user_team_winners(): void
    {
        $this->runGala();

        $trophies = ManagerTrophy::where('game_id', $this->game->id)
            ->where('trophy_type', 'award')
            ->get();

        // Balón de Oro, Zamora and MVP winners play for the user's team;
        // the Pichichi (rival) must NOT land in the manager's palmarés.
        $this->assertCount(3, $trophies);
        $names = $trophies->pluck('custom_name')->all();
        $this->assertContains('Balón de Oro', $names);
        $this->assertContains('Zamora', $names);
        $this->assertContains('MVP', $names);
        $this->assertNotContains('Pichichi', $names);
        $this->assertTrue($trophies->every(fn ($t) => $t->team_id === $this->userTeam->id));
    }

    public function test_gala_publishes_notification_and_media_news(): void
    {
        $this->runGala();

        $notification = GameNotification::where('game_id', $this->game->id)
            ->where('type', GameNotification::TYPE_AWARDS_GALA)
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Gala', $notification->title);
        $this->assertStringContainsString('Delantera Estrella', $notification->message);
        $this->assertStringContainsString('Rival Goleadora', $notification->message);
        $this->assertStringContainsString('Portera Muro', $notification->message);

        $post = SocialPost::where('game_id', $this->game->id)
            ->where('context', 'awards_gala')
            ->first();
        $this->assertNotNull($post);
        $this->assertStringContainsString('GALA', $post->text);
        $this->assertStringContainsString('Balón de Oro', $post->text);
    }

    public function test_gala_stores_winners_in_transition_metadata(): void
    {
        $data = $this->runGala();

        $gala = $data->getMetadata('awards_gala');
        $this->assertIsArray($gala);
        $this->assertArrayHasKey(SeasonAward::AWARD_PICHICHI, $gala);
        $this->assertArrayHasKey(SeasonAward::AWARD_ZAMORA, $gala);
        $this->assertArrayHasKey(SeasonAward::AWARD_MVP, $gala);
        $this->assertArrayHasKey(SeasonAward::AWARD_BALLON_DOR, $gala);
        $this->assertEquals('Rival Goleadora', $gala[SeasonAward::AWARD_PICHICHI]['name']);
    }

    public function test_gala_is_idempotent_on_retry(): void
    {
        $this->runGala();
        $this->runGala();

        $this->assertEquals(
            4,
            SeasonAward::where('game_id', $this->game->id)->count()
        );
        $this->assertEquals(
            3,
            ManagerTrophy::where('game_id', $this->game->id)->where('trophy_type', 'award')->count()
        );
    }

    public function test_gala_skips_tournament_games(): void
    {
        $tournamentGame = Game::factory()->forTeam($this->userTeam)->create([
            'user_id' => $this->user->id,
            'competition_id' => $this->competition->id,
            'season' => '2025',
            'game_mode' => Game::MODE_TOURNAMENT,
        ]);

        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $this->competition->id,
        );

        $this->processor()->process($tournamentGame, $data);

        $this->assertEquals(0, SeasonAward::where('game_id', $tournamentGame->id)->count());
        $this->assertEquals(
            0,
            GameNotification::where('game_id', $tournamentGame->id)
                ->where('type', GameNotification::TYPE_AWARDS_GALA)
                ->count()
        );
    }
}
