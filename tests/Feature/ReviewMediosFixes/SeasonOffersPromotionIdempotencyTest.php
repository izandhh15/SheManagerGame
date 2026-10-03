<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Http\Views\ShowSeasonOffers;
use App\Models\Game;
use App\Models\ManagerJobOffer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Manager\Services\AcademyCareerService;
use App\Modules\Manager\Services\JobOfferService;
use App\Modules\Match\Services\MatchFinalizationService;
use App\Modules\Report\Services\SeasonSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use ReflectionMethod;
use Tests\TestCase;

/**
 * TRIAGE-B G15: maybeCreatePromotionOffer() was a check-then-create on a
 * GET with no transaction or lock — two concurrent page loads duplicated
 * the academy promotion offer. The check now runs with lockForUpdate()
 * inside a transaction.
 */
class SeasonOffersPromotionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function academyGame(User $user, Team $reserveTeam): Game
    {
        return Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $reserveTeam->id,
            'game_mode' => Game::MODE_CAREER,
            'academy_career_club_id' => $reserveTeam->parent_team_id,
            'season' => '2025',
            'current_date' => '2025-06-01',
        ]);
    }

    /**
     * Build the view with mocked services — notably WITHOUT resolving
     * PlayoffGeneratorFactory from the container (unrelated to this fix).
     */
    private function makeView(?Team $promotionTarget): ShowSeasonOffers
    {
        /** @var AcademyCareerService&MockInterface $academy */
        $academy = $this->mock(AcademyCareerService::class);
        $academy->shouldReceive('rollForPromotion')->andReturn($promotionTarget);

        return new ShowSeasonOffers(
            $this->createMock(SeasonSummaryService::class),
            $this->createMock(JobOfferService::class),
            $this->createMock(MatchFinalizationService::class),
            $this->createMock(PlayoffGeneratorFactory::class),
            $academy,
        );
    }

    private function invokeMaybeCreate(ShowSeasonOffers $view, Game $game): ?ManagerJobOffer
    {
        $method = new ReflectionMethod(ShowSeasonOffers::class, 'maybeCreatePromotionOffer');
        $method->setAccessible(true);

        return $method->invoke($view, $game);
    }

    private function promotionOfferCount(Game $game): int
    {
        return ManagerJobOffer::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('offer_type', ManagerJobOffer::TYPE_ACADEMY_PROMOTION)
            ->count();
    }

    public function test_double_invocation_creates_single_offer(): void
    {
        $user = User::factory()->create();
        $parent = Team::factory()->create();
        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);
        $game = $this->academyGame($user, $reserve);

        $view = $this->makeView($parent);

        $first = $this->invokeMaybeCreate($view, $game);
        $second = $this->invokeMaybeCreate($view, $game);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $this->promotionOfferCount($game));
    }

    public function test_existing_non_pending_offer_is_not_returned(): void
    {
        $user = User::factory()->create();
        $parent = Team::factory()->create();
        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);
        $game = $this->academyGame($user, $reserve);

        ManagerJobOffer::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'team_id' => $parent->id,
            'season' => $game->season,
            'offer_type' => ManagerJobOffer::TYPE_ACADEMY_PROMOTION,
            'status' => ManagerJobOffer::STATUS_ACCEPTED,
            'target_reputation_level' => 'promotion',
            'created_on_game_date' => $game->current_date,
        ]);

        $view = $this->makeView($parent);

        $this->assertNull($this->invokeMaybeCreate($view, $game));
        $this->assertSame(1, $this->promotionOfferCount($game));
    }

    public function test_no_offer_when_roll_fails(): void
    {
        $user = User::factory()->create();
        $parent = Team::factory()->create();
        $reserve = Team::factory()->create(['parent_team_id' => $parent->id]);
        $game = $this->academyGame($user, $reserve);

        $view = $this->makeView(null);

        $this->assertNull($this->invokeMaybeCreate($view, $game));
        $this->assertSame(0, $this->promotionOfferCount($game));
    }
}
