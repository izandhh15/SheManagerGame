<?php

namespace Tests\Unit;

use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * BAJA review: MatchSimulator::processInjurySubstitution() clamped the
 * injury-sub minute to a hardcoded 93 while event generation uses
 * REGULATION_UPPER_BOUND (95). The clamp now uses the constant.
 */
class InjurySubMinuteClampTest extends TestCase
{
    use RefreshDatabase;

    public function test_injury_sub_minute_clamps_to_regulation_upper_bound(): void
    {
        $team = Team::factory()->create(['name' => 'Home FC']);

        $injured = GamePlayer::factory()->create([
            'team_id' => $team->id,
            'position' => 'Centre-Forward',
            'overall_score' => 80,
            'date_of_birth' => '1998-06-15',
        ]);
        $replacement = GamePlayer::factory()->create([
            'team_id' => $team->id,
            'position' => 'Centre-Forward',
            'overall_score' => 75,
            'date_of_birth' => '2000-02-20',
        ]);

        // Injury at 94': sub would be at 95' — the old hardcoded clamp put
        // it at 93', below the constant the rest of the simulator uses.
        $injuries = new Collection([
            MatchEventData::injury($team->id, $injured->id, 94, 'knock', 1),
        ]);

        [$subEvents] = $this->process(
            $team->id,
            $injuries,
            new Collection([$injured]),
            new Collection([$replacement]),
        );

        $this->assertCount(1, $subEvents);
        $this->assertSame(MatchSimulator::REGULATION_UPPER_BOUND, $subEvents->first()->minute);
        $this->assertSame(95, $subEvents->first()->minute);
    }

    public function test_early_injury_sub_uses_injury_minute_plus_one(): void
    {
        $team = Team::factory()->create(['name' => 'Home FC']);

        $injured = GamePlayer::factory()->create([
            'team_id' => $team->id,
            'position' => 'Centre-Back',
            'overall_score' => 80,
            'date_of_birth' => '1998-06-15',
        ]);
        $replacement = GamePlayer::factory()->create([
            'team_id' => $team->id,
            'position' => 'Centre-Back',
            'overall_score' => 75,
            'date_of_birth' => '2000-02-20',
        ]);

        $injuries = new Collection([
            MatchEventData::injury($team->id, $injured->id, 30, 'knock', 1),
        ]);

        [$subEvents, $lineup, $bench] = $this->process(
            $team->id,
            $injuries,
            new Collection([$injured]),
            new Collection([$replacement]),
        );

        $this->assertSame(31, $subEvents->first()->minute);
        $this->assertSame([$replacement->id], $lineup->pluck('id')->all());
        $this->assertTrue($bench->isEmpty());
    }

    private function process(string $teamId, Collection $injuries, Collection $lineup, Collection $bench): array
    {
        $service = app(MatchSimulator::class);
        $method = new \ReflectionMethod($service, 'processInjurySubstitution');
        $method->setAccessible(true);

        return $method->invoke($service, $teamId, $injuries, $lineup, $bench);
    }
}
