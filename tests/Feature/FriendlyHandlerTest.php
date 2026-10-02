<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Modules\Competition\Services\CompetitionHandlerResolver;
use App\Modules\Match\Handlers\FriendlyHandler;
use Tests\TestCase;

class FriendlyHandlerTest extends TestCase
{
    public function test_friendly_handler_is_registered(): void
    {
        // Regression: advancing to a government-sponsored friendly threw
        // "No competition handler registered for type: friendly" (500).
        $resolver = app(CompetitionHandlerResolver::class);
        $this->assertTrue($resolver->hasHandler('friendly'));

        $competition = new Competition(['handler_type' => 'friendly', 'type' => 'cup']);
        $handler = $resolver->resolve($competition);
        $this->assertInstanceOf(FriendlyHandler::class, $handler);
        $this->assertSame('friendly', $handler->getType());
    }
}
