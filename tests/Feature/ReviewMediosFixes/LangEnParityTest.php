<?php

namespace Tests\Feature\ReviewMediosFixes;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M-review fix 6/6 (grupo 19, TRIAGE-B): paridad de claves es↔en en los
 * ficheros de idioma tocados. El inglés es `fallback_locale`: cada clave
 * faltante en lang/en/*.php es una raw key visible para todos los usuarios
 * no-españoles.
 *
 * No necesita la app Laravel: carga los ficheros PHP de lang directamente.
 */
class LangEnParityTest extends TestCase
{
    /** @var string[] */
    private const FILES = [
        'app',
        'club',
        'finances',
        'game',
        'messages',
        'notifications',
        'squad',
        'transfers',
        'season',
    ];

    /**
     * Claves añadidas por este fix (notación punteada), por fichero.
     * En club.php van anidadas bajo `commercial.`.
     *
     * @var array<string, string[]>
     */
    private const NEW_KEYS = [
        'app' => ['weekday_mon', 'weekday_tue', 'weekday_wed', 'weekday_thu', 'weekday_fri', 'weekday_sat', 'weekday_sun'],
        'club' => [
            'commercial.slot_shirt', 'commercial.slot_ad_board',
            'commercial.shirt_title', 'commercial.shirt_intro',
            'commercial.ad_board_title', 'commercial.ad_board_intro',
            'commercial.offers_title',
            'commercial.tier_badge_local', 'commercial.tier_badge_regional',
            'commercial.tier_badge_nacional', 'commercial.tier_badge_internacional',
            'commercial.annual_value', 'commercial.contract_length',
            'commercial.seasons', 'commercial.seasons_remaining',
            'commercial.accept_button', 'commercial.reject_button',
            'commercial.accept_confirm', 'commercial.reject_confirm',
            'commercial.renewal_badge', 'commercial.active_deal_label',
        ],
        'finances' => ['shirt_sponsor', 'ad_board', 'tooltip_shirt_sponsor', 'tooltip_ad_board', 'tx_severance_installment', 'tx_severance_loan_received'],
        'game' => [
            'list_view', 'month_view',
            'pro_start_scratch', 'pro_start_academy',
            'academy_pick_intro', 'academy_start_at', 'no_academy_clubs',
            'academy_promotion_title', 'academy_promotion_desc', 'academy_promotion_subtitle', 'academy_promotion_accept',
            'job_market_title', 'job_market_intro', 'job_market_warning', 'job_market_available', 'job_market_empty',
            'job_apply', 'job_accepted_title', 'job_accepted_desc', 'job_accepted_discovered_desc',
            'job_rejected_title', 'job_rejected_desc', 'job_betrayal_title', 'job_betrayal_desc',
            'preseason_family_derby_banner_title', 'preseason_family_derby_banner_body',
            'preseason_family_derby_round', 'preseason_family_derby_trophy',
            'preseason_tour_not_eligible', 'venue_request_accepted',
            'mens_stadium_rejected_excuse_laliga', 'mens_stadium_rejected_excuse_grass',
            'mens_stadium_rejected_excuse_concert', 'mens_stadium_rejected_excuse_maintenance',
            'mens_stadium_rejected_excuse_reserve', 'mens_stadium_not_in_country',
            'mens_stadium_council_label', 'mens_stadium_rejected_excuse_pitch',
            'mens_stadium_rejected_excuse_works', 'mens_stadium_rejected_excuse_derby',
            'mens_stadium_rejected_excuse_board',
            'gov_friendly_page_title', 'gov_friendly_badge', 'gov_friendly_offer_title', 'gov_friendly_offer_body',
            'gov_friendly_government', 'gov_friendly_opponent', 'gov_friendly_fee',
            'gov_friendly_accept', 'gov_friendly_reject', 'gov_friendly_no_offer', 'gov_friendly_back',
            'gov_friendly_round_name', 'gov_friendly_accepted_title', 'gov_friendly_accepted_body',
            'gov_friendly_accepted_flash', 'gov_friendly_rejected_flash',
            'gov_friendly_already_answered', 'gov_friendly_invalid',
            'gov_venue_badge', 'gov_venue_offer_title', 'gov_venue_offer_body', 'gov_venue_free',
            'gov_venue_accept', 'gov_venue_reject', 'gov_venue_no_offer',
            'gov_venue_accepted_flash', 'gov_venue_rejected_flash',
            'gov_venue_already_answered', 'gov_venue_invalid', 'gov_venue_invalid_stadium',
            'subsidy_city_council', 'subsidy_breakdown_title',
        ],
        'messages' => [
            'mutual_termination_completed', 'severance_invalid_method', 'severance_loan_active', 'severance_loan_unavailable',
            'cannot_apply_to_own_club',
            'naming_rights_offer_rejected', 'sponsor_deal_accepted', 'sponsor_deal_rejected',
            'sponsor_offer_unavailable', 'sponsor_deal_active',
        ],
        'notifications' => [
            'academy_jewel_title', 'academy_jewel_message',
            'mutual_termination_title', 'mutual_termination_message',
            'severance_plan_title', 'severance_plan_message',
            'severance_plan_completed_title', 'severance_plan_completed_message',
            'stadium_request_title', 'stadium_request_message',
            'stadium_request_accepted_title', 'stadium_request_accepted_message',
            'stadium_request_rejected_title', 'stadium_request_rejected_message',
            'sponsor_offers_arrived_title', 'sponsor_offers_arrived_message',
            'cwc_qualified_title', 'cwc_qualified_message',
        ],
        'squad' => ['mutual_terminate', 'academy_jewel', 'academy_jewel_tooltip'],
        'transfers' => [
            'chat_agent_rival_offer', 'chat_agent_rival_pressure', 'chat_agent_rival_match',
            'chat_agent_rival_rejected', 'chat_agent_impatient', 'chat_agent_patience_low',
            'chat_rival_offer_title',
        ],
        'season' => [
            'top_scorer_argentine', 'best_goalkeeper_argentine',
            'top_scorer_brazilian', 'best_goalkeeper_brazilian',
            'top_scorer_mexican', 'best_goalkeeper_mexican',
            'top_scorer_american', 'best_goalkeeper_american',
        ],
    ];

    private static function base(): string
    {
        return dirname(__DIR__, 3) . '/lang';
    }

    /** @return array<string, string> clave punteada => valor */
    private static function flat(array $arr, string $prefix = ''): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $out += self::flat($v, $prefix . $k . '.');
            } else {
                $out[$prefix . $k] = (string) $v;
            }
        }
        return $out;
    }

    /** @return array<string, string> */
    private static function es(string $file): array
    {
        return self::flat(require self::base() . "/es/{$file}.php");
    }

    /** @return array<string, string> */
    private static function en(string $file): array
    {
        return self::flat(require self::base() . "/en/{$file}.php");
    }

    /** @return array<string, string> lista de :placeholders en orden de aparición */
    private static function placeholders(string $value): array
    {
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $value, $m);
        return $m[0];
    }

    public function test_knockout_qualified_not_duplicated_and_feminine(): void
    {
        $raw = file_get_contents(self::base() . '/es/game.php');
        $this->assertSame(
            1,
            substr_count($raw, "'knockout_qualified'"),
            'lang/es/game.php no debe tener knockout_qualified duplicada'
        );

        $es = self::es('game');
        $this->assertSame(
            'Clasificada a eliminatorias',
            $es['knockout_qualified'],
            'knockout_qualified debe conservar la forma femenina'
        );
    }

    #[DataProvider('filesProvider')]
    public function test_english_has_every_spanish_key(string $file): void
    {
        $missing = array_diff_key(self::es($file), self::en($file));
        $this->assertSame(
            [],
            $missing,
            "lang/en/{$file}.php: faltan " . count($missing) . ' claves: '
                . implode(', ', array_keys($missing))
        );
    }

    #[DataProvider('filesProvider')]
    public function test_no_extra_english_keys(string $file): void
    {
        $extra = array_diff_key(self::en($file), self::es($file));
        $this->assertSame(
            [],
            $extra,
            "lang/en/{$file}.php: sobran " . count($extra) . ' claves: '
                . implode(', ', array_keys($extra))
        );
    }

    /**
     * Los :placeholders de las claves NUEVAS deben ser EXACTOS en en
     * (el código los sustituye por nombre). Solo se chequean las claves
     * añadidas por este fix: el resto del repo tiene desajustes preexistentes
     * fuera del alcance del grupo 19.
     */
    #[DataProvider('newKeysProvider')]
    public function test_new_keys_placeholders_match_es_en(string $file, string $key): void
    {
        $es = self::es($file);
        $en = self::en($file);
        $this->assertArrayHasKey($key, $es, "lang/es/{$file}.php no tiene la clave {$key}");
        $this->assertArrayHasKey($key, $en, "lang/en/{$file}.php no tiene la clave {$key}");
        $this->assertSame(
            self::placeholders($es[$key]),
            self::placeholders($en[$key]),
            "lang/en/{$file}.php: placeholders distintos en {$key}"
        );
    }

    /** @return array<string, array{string, string}> */
    public static function newKeysProvider(): array
    {
        $out = [];
        foreach (self::NEW_KEYS as $file => $keys) {
            foreach ($keys as $key) {
                $out["{$file}.{$key}"] = [$file, $key];
            }
        }
        return $out;
    }

    /** @return array<string, array{string}> */
    public static function filesProvider(): array
    {
        $out = [];
        foreach (self::FILES as $f) {
            $out[$f] = [$f];
        }
        return $out;
    }
}
