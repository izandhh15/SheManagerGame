<?php

namespace Tests\Feature\ReviewAltosFixes;

use App\Modules\Competition\Configs\QualifyingPlayoffConfig;
use App\Modules\Competition\Contracts\CompetitionConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R8 [ALTA] — QualifyingPlayoffConfig::getPositionFactor() declared
 * `int|float` while the CompetitionConfig contract requires `: float`.
 * PHP treats a wider union return type as incompatible → fatal at class
 * load → 500 on UCLQ/UELQ pages.
 */
class R8QualifyingPlayoffConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_class_loads_without_fatal(): void
    {
        // Class load itself would fatal before the fix.
        $config = new QualifyingPlayoffConfig();

        $this->assertInstanceOf(CompetitionConfig::class, $config);
    }

    public function test_get_position_factor_signature_is_float(): void
    {
        $method = new \ReflectionMethod(QualifyingPlayoffConfig::class, 'getPositionFactor');

        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame('float', (string) $returnType);
    }

    public function test_get_position_factor_returns_float(): void
    {
        $config = new QualifyingPlayoffConfig();

        $value = $config->getPositionFactor(1);

        $this->assertIsFloat($value);
        $this->assertSame(1.0, $value);
    }
}
