<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Http\Views\ShowSquadRegistration;
use App\Http\Views\ShowSquadSelection;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * R17 [ALTA]: date_of_birth is nullable, but ShowSquadRegistration
 * (age()/is_u23) and ShowSquadSelection (candidate list) dereferenced it
 * without a guard → 500 for any player without a birth date.
 * Both now use ?-> with a '—' fallback (ShowPlayerDetail already did).
 */
class R17NullDobTest extends TestCase
{
    use RefreshDatabase;

    public function test_squad_registration_renders_player_without_birth_date(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'game_mode' => Game::MODE_CAREER,
            'current_date' => '2026-08-15',
        ]);

        $player = GamePlayer::factory()
            ->forGame($game)
            ->forTeam($team)
            ->create(['date_of_birth' => null]);

        // Manual invokable call (container resolves method injection).
        $view = $this->app->call(ShowSquadRegistration::class.'@__invoke', [
            'gameId' => $game->id,
        ]);

        $players = $view->getData()['players'];

        $this->assertArrayHasKey($player->id, $players);
        $this->assertSame('—', $players[$player->id]['age']);
        $this->assertFalse($players[$player->id]['is_u23']);
    }

    public function test_squad_selection_candidate_without_birth_date_does_not_crash(): void
    {
        $user = User::factory()->create();
        // 14161.json (Haiti, men's WC2026 data) exists in the repo — its
        // player ids are used to build the candidate list.
        $team = Team::factory()->create([
            'type' => 'club',
            'is_placeholder' => false,
            'transfermarkt_id' => '14161',
        ]);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        GamePlayerTemplate::create([
            'season' => '2026',
            'player_id' => (string) Str::uuid(),
            'transfermarkt_id' => '838947',
            'team_id' => $team->id,
            'name' => 'Null Dob Player',
            'position' => 'Goalkeeper',
            'date_of_birth' => null,
            'overall_score' => 70,
        ]);

        $view = app(ShowSquadSelection::class);
        $method = new \ReflectionMethod($view, 'loadCandidates');
        $method->setAccessible(true);

        // Must not throw on the null date_of_birth.
        $groups = $method->invoke($view, $game->fresh());

        $candidate = collect($groups)
            ->flatten(1)
            ->firstWhere('transfermarkt_id', '838947');

        $this->assertNotNull($candidate, 'Candidate should be built from the template');
        $this->assertSame('—', $candidate['age']);
    }
}
