<?php

namespace Tests\Unit;

use App\Models\GameStanding;
use App\Modules\Competition\DTOs\QualificationStatus;
use App\Modules\Competition\ProgressResolvers\SwissProgressResolver;
use Tests\TestCase;

/**
 * BAJA review: SwissProgressResolver hardcoded the 8/24 cuts as class
 * constants (wrong for the 20-team UEL: 21st+ doesn't exist, and nobody is
 * eliminated at the league-phase cut) and `null <= 8` evaluated to true,
 * marking an unordered standing as "advanced". Cuts are now per
 * competition and a null position resolves to null.
 */
class SwissProgressResolverTest extends TestCase
{
    private function standing(?int $position): GameStanding
    {
        $standing = new GameStanding;
        $standing->position = $position;

        return $standing;
    }

    public function test_null_position_resolves_to_null(): void
    {
        $resolver = app(SwissProgressResolver::class);

        $this->assertNull($resolver->resolve('game-1', 'UCL', $this->standing(null)));
        $this->assertNull($resolver->resolve('game-1', 'UEL', $this->standing(null)));
    }

    public function test_ucl_cuts_28_teams(): void
    {
        $resolver = app(SwissProgressResolver::class);

        $this->assertSame(QualificationStatus::Advanced, $resolver->resolve('g', 'UCL', $this->standing(8))->status);
        $this->assertSame(QualificationStatus::Playoff, $resolver->resolve('g', 'UCL', $this->standing(9))->status);
        $this->assertSame(QualificationStatus::Playoff, $resolver->resolve('g', 'UCL', $this->standing(24))->status);
        $this->assertSame(QualificationStatus::Eliminated, $resolver->resolve('g', 'UCL', $this->standing(25))->status);
    }

    public function test_uel_cuts_20_teams_with_no_eliminations(): void
    {
        $resolver = app(SwissProgressResolver::class);

        $this->assertSame(QualificationStatus::Advanced, $resolver->resolve('g', 'UEL', $this->standing(8))->status);
        $this->assertSame(QualificationStatus::Playoff, $resolver->resolve('g', 'UEL', $this->standing(9))->status);
        // 20th still makes the playoff; the old shared 24-cut is gone.
        $this->assertSame(QualificationStatus::Playoff, $resolver->resolve('g', 'UEL', $this->standing(20))->status);
    }

    public function test_unknown_competition_falls_back_to_ucl_cuts(): void
    {
        $resolver = app(SwissProgressResolver::class);

        $this->assertSame(QualificationStatus::Playoff, $resolver->resolve('g', 'NOPE', $this->standing(24))->status);
        $this->assertSame(QualificationStatus::Eliminated, $resolver->resolve('g', 'NOPE', $this->standing(25))->status);
    }
}
