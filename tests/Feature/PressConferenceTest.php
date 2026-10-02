<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\GameStanding;
use App\Models\PreMatchPress;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\PressConferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pre-match press conferences ("Rueda de prensa").
 *
 * Before big matches (finals, derbies, european nights, clashes with direct
 * rivals) the manager faces 2-3 journalist questions with 3 answers each.
 * Answers move squad morale and board confidence through the existing
 * pressure system — one press conference per match.
 */
class PressConferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_triggers_press_conference_with_three_questions(): void
    {
        [$game, $user, $match] = $this->buildScenario(
            homeCountry: 'ES',
            awayCountry: 'FR',
            roundName: 'Final',
            cupTie: true,
        );

        $press = app(PressConferenceService::class);

        $this->assertTrue($press->isBigMatch($game, $match));
        $this->assertContains('final', $press->bigMatchReasons($game, $match));

        $response = $this->actingAs($user)->get(route('game.pre-press', [$game->id, $match->id]));
        $response->assertOk();

        // 3 questions, each rendered with its radio group.
        $questions = $press->questions($game, $match);
        $this->assertCount(3, $questions);
        $this->assertSame('importance', $questions[0]['key']);
        foreach ($questions as $question) {
            $this->assertCount(3, $question['answers']);
            $response->assertSee('name="answers['.$question['key'].']"', false);
            foreach ($question['answers'] as $answer) {
                $this->assertNotEmpty($answer['key']);
                $this->assertNotEmpty($answer['label']);
            }
        }
    }

    public function test_european_match_triggers_press_conference(): void
    {
        $competition = Competition::factory()->create(['role' => Competition::ROLE_EUROPEAN]);
        [$game, $user, $match] = $this->buildScenario(
            homeCountry: 'ES',
            awayCountry: 'EN',
            competition: $competition,
        );

        $press = app(PressConferenceService::class);

        $this->assertTrue($press->isBigMatch($game, $match));
        $this->assertContains('european', $press->bigMatchReasons($game, $match));

        $this->actingAs($user)->get(route('game.pre-press', [$game->id, $match->id]))->assertOk();
    }

    public function test_derby_against_direct_rival_triggers_press_conference(): void
    {
        [$game, $user, $match] = $this->buildScenario(
            homeCountry: 'ES',
            awayCountry: 'ES',
            roundNumber: 5,
            withStandings: true,
        );

        $press = app(PressConferenceService::class);
        $reasons = $press->bigMatchReasons($game, $match);

        $this->assertContains('derby', $reasons);
        $this->assertContains('rival', $reasons);
        $this->assertTrue($press->isBigMatch($game, $match));

        $this->actingAs($user)->get(route('game.pre-press', [$game->id, $match->id]))->assertOk();
    }

    public function test_ordinary_match_has_no_press_conference(): void
    {
        [$game, $user, $match] = $this->buildScenario(
            homeCountry: 'ES',
            awayCountry: 'FR',
        );

        $press = app(PressConferenceService::class);

        $this->assertFalse($press->isBigMatch($game, $match));
        $this->assertSame([], $press->bigMatchReasons($game, $match));

        $this->actingAs($user)
            ->get(route('game.pre-press', [$game->id, $match->id]))
            ->assertRedirect(route('game.lineup', $game->id));
    }

    public function test_bold_answers_raise_morale_and_confidence(): void
    {
        [$game, $user, $match, $player] = $this->buildScenario(
            homeCountry: 'ES',
            awayCountry: 'ES',
            roundNumber: 5,
            withStandings: true,
            withPlayer: true,
        );

        $press = app(PressConferenceService::class);
        $questions = $press->questions($game, $match);

        // Pick the first (boldest) answer of every question.
        $answers = [];
        $expectedMorale = 80;
        $expectedConfidence = 70;
        foreach ($questions as $question) {
            $answers[$question['key']] = $question['answers'][0]['key'];
            $expectedMorale += $question['answers'][0]['morale'];
            $expectedConfidence += $question['answers'][0]['confidence'];
        }

        $this->actingAs($user)
            ->post(route('game.pre-press.submit', [$game->id, $match->id]), ['answers' => $answers])
            ->assertRedirect(route('game.lineup', $game->id));

        $record = PreMatchPress::where('game_id', $game->id)->where('match_id', $match->id)->first();
        $this->assertNotNull($record);
        $this->assertSame($expectedMorale - 80, $record->morale_delta);
        $this->assertSame($expectedConfidence - 70, $record->confidence_delta);

        $this->assertSame($expectedConfidence, $game->refresh()->board_confidence);
        $this->assertSame(
            $expectedMorale,
            (int) GamePlayerMatchState::where('game_player_id', $player->id)->value('morale'),
        );
    }

    public function test_negative_answers_hurt_morale_and_confidence(): void
    {
        [$game, $user, $match, $player] = $this->buildScenario(
            homeCountry: 'ES',
            awayCountry: 'ES',
            roundNumber: 5,
            withStandings: true,
            withPlayer: true,
        );

        $press = app(PressConferenceService::class);
        $questions = $press->questions($game, $match);

        // Pick the last (most defeatist) answer of every question.
        $answers = [];
        $expectedMorale = 80;
        $expectedConfidence = 70;
        foreach ($questions as $question) {
            $last = $question['answers'][count($question['answers']) - 1];
            $answers[$question['key']] = $last['key'];
            $expectedMorale += $last['morale'];
            $expectedConfidence += $last['confidence'];
        }

        $this->assertLessThan(80, $expectedMorale);
        $this->assertLessThan(70, $expectedConfidence);

        $this->actingAs($user)
            ->post(route('game.pre-press.submit', [$game->id, $match->id]), ['answers' => $answers])
            ->assertRedirect(route('game.lineup', $game->id));

        $this->assertSame($expectedConfidence, $game->refresh()->board_confidence);
        $this->assertSame(
            $expectedMorale,
            (int) GamePlayerMatchState::where('game_player_id', $player->id)->value('morale'),
        );
    }

    public function test_cannot_answer_twice_for_the_same_match(): void
    {
        [$game, $user, $match, $player] = $this->buildScenario(
            homeCountry: 'ES',
            awayCountry: 'ES',
            roundNumber: 5,
            withStandings: true,
            withPlayer: true,
        );

        $press = app(PressConferenceService::class);
        $questions = $press->questions($game, $match);
        $answers = [];
        foreach ($questions as $question) {
            $answers[$question['key']] = $question['answers'][0]['key'];
        }

        $post = fn () => $this->actingAs($user)
            ->post(route('game.pre-press.submit', [$game->id, $match->id]), ['answers' => $answers]);

        $post()->assertRedirect(route('game.lineup', $game->id));
        $moraleAfterFirst = (int) GamePlayerMatchState::where('game_player_id', $player->id)->value('morale');
        $confidenceAfterFirst = $game->refresh()->board_confidence;

        // Second submit: no double effects.
        $post()->assertRedirect(route('game.lineup', $game->id));
        $this->assertSame($moraleAfterFirst, (int) GamePlayerMatchState::where('game_player_id', $player->id)->value('morale'));
        $this->assertSame($confidenceAfterFirst, $game->refresh()->board_confidence);
        $this->assertSame(1, PreMatchPress::where('game_id', $game->id)->where('match_id', $match->id)->count());

        // The page now shows the answered summary.
        $this->actingAs($user)
            ->get(route('game.pre-press', [$game->id, $match->id]))
            ->assertOk()
            ->assertSee('Ya atendiste');
    }

    public function test_tampered_answer_key_is_rejected(): void
    {
        [$game, $user, $match] = $this->buildScenario(
            homeCountry: 'ES',
            awayCountry: 'FR',
            roundName: 'Final',
            cupTie: true,
        );

        $this->actingAs($user)
            ->post(route('game.pre-press.submit', [$game->id, $match->id]), [
                'answers' => ['importance' => 'hacked', 'final' => 'winners', 'fans' => 'trust'],
            ])
            ->assertSessionHasErrors('answers.importance');

        $this->assertSame(0, PreMatchPress::where('game_id', $game->id)->count());
        $this->assertSame(70, $game->refresh()->board_confidence);
    }

    public function test_other_users_cannot_answer(): void
    {
        [$game, , $match] = $this->buildScenario(
            homeCountry: 'ES',
            awayCountry: 'FR',
            roundName: 'Final',
            cupTie: true,
        );
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->post(route('game.pre-press.submit', [$game->id, $match->id]), [
                'answers' => ['importance' => 'all_in', 'final' => 'winners', 'fans' => 'trust'],
            ])
            ->assertForbidden();

        $this->assertSame(0, PreMatchPress::where('game_id', $game->id)->count());
    }

    public function test_match_from_another_game_returns_404(): void
    {
        [$game, $user] = $this->buildScenario(homeCountry: 'ES', awayCountry: 'FR', roundName: 'Final', cupTie: true);
        $otherMatch = GameMatch::factory()->create();

        $this->actingAs($user)
            ->get(route('game.pre-press', [$game->id, $otherMatch->id]))
            ->assertNotFound();
    }

    /**
     * @return array{Game, User, GameMatch, ?GamePlayer}
     */
    private function buildScenario(
        string $homeCountry,
        string $awayCountry,
        ?Competition $competition = null,
        int $roundNumber = 1,
        string $roundName = 'Matchday 1',
        bool $cupTie = false,
        bool $withStandings = false,
        bool $withPlayer = false,
    ): array {
        $user = User::factory()->create();
        $home = Team::factory()->create(['country' => $homeCountry]);
        $away = Team::factory()->create(['country' => $awayCountry]);
        $competition ??= Competition::factory()->league();

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $home->id,
            'competition_id' => $competition->id,
            'board_confidence' => 70,
        ]);

        $match = GameMatch::factory()->forGame($game)->create([
            'competition_id' => $competition->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'round_number' => $roundNumber,
            'round_name' => $roundName,
            'cup_tie_id' => $cupTie ? Str::uuid()->toString() : null,
            'played' => false,
        ]);

        if ($withStandings) {
            GameStanding::create([
                'game_id' => $game->id,
                'competition_id' => $competition->id,
                'team_id' => $home->id,
                'position' => 2,
                'points' => 12,
            ]);
            GameStanding::create([
                'game_id' => $game->id,
                'competition_id' => $competition->id,
                'team_id' => $away->id,
                'position' => 3,
                'points' => 10,
            ]);
        }

        $player = null;
        if ($withPlayer) {
            $player = GamePlayer::factory()->create([
                'game_id' => $game->id,
                'team_id' => $home->id,
            ]);
            // The factory seeds match state with random morale; pin it.
            GamePlayerMatchState::where('game_player_id', $player->id)->update(['morale' => 80]);
        }

        return [$game, $user, $match, $player];
    }
}
