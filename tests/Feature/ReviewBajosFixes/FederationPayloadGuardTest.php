<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Modules\Social\Services\FederationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BAJA validación: FederationService::handleIncomingRequest() leía
 * $data['to_username'] sin isset → 500 con payload malformado.
 */
class FederationPayloadGuardTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('malformedPayloads')]
    public function test_malformed_payload_returns_error_not_500(array $payload): void
    {
        $result = app(FederationService::class)->handleIncomingRequest($payload);

        $this->assertSame(['ok' => false, 'error' => 'invalid_payload'], $result);
    }

    public static function malformedPayloads(): array
    {
        return [
            'empty' => [[]],
            'missing to_username' => [['from_username' => 'a', 'from_instance' => 'b']],
            'missing from_username' => [['to_username' => 'a', 'from_instance' => 'b']],
            'missing from_instance' => [['to_username' => 'a', 'from_username' => 'b']],
            'non-string to_username' => [['to_username' => 123, 'from_username' => 'b', 'from_instance' => 'c']],
            'null values' => [['to_username' => null, 'from_username' => null, 'from_instance' => null]],
        ];
    }

    public function test_well_formed_payload_with_unknown_user_returns_user_not_found(): void
    {
        $result = app(FederationService::class)->handleIncomingRequest([
            'to_username' => 'nobody-here',
            'from_username' => 'remote-user',
            'from_instance' => 'remote.example',
        ]);

        $this->assertSame(['ok' => false, 'error' => 'user_not_found'], $result);
    }
}
