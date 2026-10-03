<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Game;
use App\Models\PreseasonInvitation;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Services\PreseasonInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fix 11: PreseasonInvitationService::accept() runs in a transaction with
 * the slot's invitations locked — two concurrent accepts can't leave two
 * ACCEPTED rows in the same slot.
 */
class PreseasonInvitationAcceptTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-07-01',
        ]);
    }

    private function invitation(int $slot): PreseasonInvitation
    {
        return PreseasonInvitation::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'inviting_team_id' => Team::factory()->create()->id,
            'slot' => $slot,
            'status' => PreseasonInvitation::STATUS_PENDING,
        ]);
    }

    public function test_accept_occupies_slot_and_declines_rivals(): void
    {
        $service = app(PreseasonInvitationService::class);
        $first = $this->invitation(0);
        $second = $this->invitation(0);

        $slot = $service->accept($this->game, $first->id);

        $this->assertSame(0, $slot);
        $this->assertSame(
            PreseasonInvitation::STATUS_ACCEPTED,
            $first->fresh()->status
        );
        $this->assertSame(
            PreseasonInvitation::STATUS_DECLINED,
            $second->fresh()->status
        );
    }

    public function test_second_accept_in_taken_slot_returns_null(): void
    {
        $service = app(PreseasonInvitationService::class);
        $first = $this->invitation(1);
        $second = $this->invitation(1);

        $this->assertSame(1, $service->accept($this->game, $first->id));
        $this->assertNull($service->accept($this->game, $second->id));

        $this->assertSame(1, PreseasonInvitation::where('game_id', $this->game->id)
            ->where('slot', 1)
            ->where('status', PreseasonInvitation::STATUS_ACCEPTED)
            ->count());
    }

    public function test_accepting_same_invitation_twice_returns_slot_once(): void
    {
        $service = app(PreseasonInvitationService::class);
        $invitation = $this->invitation(2);

        $this->assertSame(2, $service->accept($this->game, $invitation->id));
        $this->assertNull($service->accept($this->game, $invitation->id));

        $this->assertSame(1, PreseasonInvitation::where('game_id', $this->game->id)
            ->where('status', PreseasonInvitation::STATUS_ACCEPTED)
            ->count());
    }
}
