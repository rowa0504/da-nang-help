<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_terms(): void
    {
        $this->get('/terms')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Terms')
                ->where('operatorName', 'Test Operator')
                ->where('contactEmail', 'privacy@example.com')
                ->where('governingLocale', 'vi')
                // Draft stage: config/legal.php ships effective_date as
                // null until the document is finalized.
                ->where('effectiveDate', null)
            );
    }

    public function test_guest_can_view_privacy(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Privacy')
                ->where('operatorName', 'Test Operator')
                ->where('contactEmail', 'privacy@example.com')
            );
    }

    /**
     * When operator_name/contact_email are unset, the props must carry
     * actual PHP/JSON null — never the three-character string "null" —
     * so the frontend's `?? ''` fallback (see LegalDocumentView) has a
     * real null to detect, not a truthy string that would print literally.
     */
    public function test_operator_name_and_contact_email_are_real_null_when_unconfigured(): void
    {
        config(['legal.operator_name' => null, 'legal.contact_email' => null]);

        $this->get('/terms')->assertInertia(fn (Assert $page) => $page
            ->where('operatorName', null)
            ->where('contactEmail', null)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function localeProvider(): array
    {
        return [
            'English' => ['en'],
            'Japanese' => ['ja'],
            'Vietnamese' => ['vi'],
        ];
    }

    #[DataProvider('localeProvider')]
    public function test_terms_renders_in_every_supported_locale(string $locale): void
    {
        $this->withSession(['locale' => $locale])->get('/terms')->assertOk();
    }

    #[DataProvider('localeProvider')]
    public function test_privacy_renders_in_every_supported_locale(string $locale): void
    {
        $this->withSession(['locale' => $locale])->get('/privacy')->assertOk();
    }

    public function test_the_production_guard_does_not_apply_outside_production(): void
    {
        // phpunit.xml's placeholder-free LEGAL_* values would otherwise
        // still pass the validator — the real point of this test is that
        // EnsureLegalConfigurationIsFinalized never even runs its check
        // outside production, which test_production_guard_blocks_*
        // below demonstrates by deliberately leaving the config broken.
        $this->get('/terms')->assertOk();
        $this->get('/privacy')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_production_guard_blocks_terms_when_legal_config_is_not_finalized(): void
    {
        app()->instance('env', 'production');
        // Default test config (set in phpunit.xml) is already finalized
        // enough to pass by accident for operator_name/contact_email, but
        // terms/privacy version still say "draft" and effective_date is
        // still null (config/legal.php's committed defaults) — exactly the
        // pre-launch state this guard exists to catch.

        $this->get('/terms')->assertStatus(503);
    }

    /**
     * The 503 body must never reveal *why* it blocked — no config key
     * names, no env values, no validator problem strings. That detail is
     * for `php artisan legal:check` output and the application log (see
     * EnsureLegalConfigurationIsFinalized's report() call), never for an
     * HTTP response a real visitor could see.
     */
    public function test_production_guard_503_response_does_not_leak_configuration_details(): void
    {
        config(['legal.operator_name' => 'not-finalized-marker-xyz']);
        app()->instance('env', 'production');

        $content = $this->get('/terms')->assertStatus(503)->getContent();

        $this->assertStringNotContainsString('not-finalized-marker-xyz', $content);
        $this->assertStringNotContainsString('operator_name', $content);
        $this->assertStringNotContainsString('legal.', $content);
        $this->assertStringNotContainsString('draft', $content);
    }

    public function test_production_guard_blocks_register_when_legal_config_is_not_finalized(): void
    {
        app()->instance('env', 'production');

        $this->get('/register')->assertStatus(503);
    }

    public function test_production_guard_allows_terms_once_legal_config_is_finalized(): void
    {
        config([
            'legal.operator_name' => 'Da Nang Help Co., Ltd.',
            'legal.contact_email' => 'privacy@danang-help.com',
            'legal.terms.version' => '2027-01-01-final',
            'legal.terms.effective_date' => '2027-01-01',
            'legal.privacy.version' => '2027-01-01-final',
            'legal.privacy.effective_date' => '2027-01-01',
        ]);
        app()->instance('env', 'production');

        $this->get('/terms')->assertOk();
        $this->get('/register')->assertOk();
    }
}
