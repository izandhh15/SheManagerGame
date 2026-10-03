<?php

namespace Tests\Unit;

use App\Models\GameStanding;
use App\Modules\Competition\DTOs\QualificationStatus;
use App\Modules\Competition\ProgressResolvers\SwissProgressResolver;
use Tests\TestCase;

/**
 * SwissProgressResolver cuts for the UWCL league phase (28 teams: 1-8 direct
 * to R16, 9-24 knockout playoff, 25-28 eliminated). A null position resolves
 * to null (`null <= 8` used to evaluate to true, marking an unordered
 * standing as "advanced").
 *
 * The Europa Cup has no cuts here: since 2026-27 it is a pure knockout and
 * never reaches this resolver (the factory keys resolvers by handler_type).
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
        $this->assertNull($resolver->resolve('game-1', 'NOPE', $this->standing(null)));
    }

    public function test_ucl_cuts_28_teams(): void
    {
        $resolver = app(SwissProgressResolver::class);

        $this->assertSame(QualificationStatus::Advanced, $resolver->resolve('g', 'UCL', $this->standing(8))->status);
        $this->assertSame(QualificationStatus::Playoff, $resolver->resolve('g', 'UCL', $this->standing(9))->status);
        $this->assertSame(QualificationStatus::Playoff, $resolver->resolve('g', 'UCL', $this->standing(24))->status);
        $this->assertSame(QualificationStatus::Eliminated, $resolver->resolve('g', 'UCL', $this->standing(25))->status);
    }

    public function test_unknown_competition_falls_back_to_ucl_cuts(): void
    {
        $resolver = app(SwissProgressResolver::class);

        $this->assertSame(QualificationStatus::Playoff, $resolver->resolve('g', 'NOPE', $this->standing(24))->status);
        $this->assertSame(QualificationStatus::Eliminated, $resolver->resolve('g', 'NOPE', $this->standing(25))->status);
    }
}
