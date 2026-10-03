<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Game;
use App\Models\SocialPost;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\NationalSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M12: los posts de periodistas aparecían en el feed oficial
 * de la selección con el badge "📢 oficial".
 *
 * Bug original: NationalSocialService::feed() incluía los contextos
 * 'journalist_preview' y 'journalist_match', y la vista
 * national-social.blade.php renderiza cada item del feed con el escudo
 * de la selección y el badge oficial: una previa de un periodista se
 * mostraba como publicada por la cuenta oficial de la federación.
 * (qa/bugs/agent-14.md)
 *
 * Fix: feed() solo devuelve 'national_official'. Los posts de
 * periodistas siguen visibles en su sitio: la sección Internet/Prensa
 * (ShowInternet).
 */
class M12OfficialFeedExcludesJournalistsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El feed oficial no incluye posts de periodistas.
     *
     * Bug original: feed() devolvía también el post 'journalist_preview'.
     */
    public function test_feed_does_not_include_journalist_posts(): void
    {
        app()->setLocale('es');

        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Spain', 'type' => 'national', 'country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'country' => 'ES',
        ])->fresh('team');

        SocialPost::create([
            'game_id' => $game->id,
            'author_name' => 'Diario Deportivo',
            'author_handle' => '@diariodeportivo',
            'text' => 'Previa del periodista: análisis del próximo partido.',
            'sentiment' => 0,
            'likes' => 100,
            'context' => 'journalist_preview',
        ]);
        SocialPost::create([
            'game_id' => $game->id,
            'author_name' => 'Selección',
            'author_handle' => '@SEFutbolFem',
            'text' => 'Comunicado oficial de la federación.',
            'sentiment' => 0,
            'likes' => 500,
            'context' => 'national_official',
        ]);

        $feed = app(NationalSocialService::class)->feed($game);

        $this->assertSame(1, $feed->count(), 'el feed oficial solo debe traer el post oficial');
        $this->assertSame('national_official', $feed->first()->context);
        $this->assertSame(
            'Comunicado oficial de la federación.',
            $feed->first()->text
        );
    }
}
