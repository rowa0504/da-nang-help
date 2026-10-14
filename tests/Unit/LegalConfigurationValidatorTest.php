<?php

namespace Tests\Unit;

use App\Support\Legal\LegalConfigurationValidator;
use Tests\TestCase;

class LegalConfigurationValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A fully finalized configuration: every individual test below
        // breaks exactly one aspect of this and asserts passes() becomes
        // false, isolating which field each failure mode is sensitive to.
        config([
            'legal.operator_name' => 'Da Nang Help Co., Ltd.',
            'legal.contact_email' => 'privacy@danang-help.com',
            'legal.terms.version' => '2027-01-01-final',
            'legal.terms.effective_date' => '2027-01-01',
            'legal.privacy.version' => '2027-01-01-final',
            'legal.privacy.effective_date' => '2027-01-01',
        ]);
    }

    public function test_fully_finalized_configuration_passes(): void
    {
        $this->assertTrue(app(LegalConfigurationValidator::class)->passes());
        $this->assertSame([], app(LegalConfigurationValidator::class)->problems());
    }

    public function test_empty_operator_name_fails(): void
    {
        config(['legal.operator_name' => null]);

        $this->assertFalse(app(LegalConfigurationValidator::class)->passes());
    }

    public function test_empty_contact_email_fails(): void
    {
        config(['legal.contact_email' => null]);

        $this->assertFalse(app(LegalConfigurationValidator::class)->passes());
    }

    public function test_development_placeholder_contact_email_fails(): void
    {
        config(['legal.contact_email' => 'privacy@danang-help.example']);

        $this->assertFalse(app(LegalConfigurationValidator::class)->passes());
    }

    public function test_development_placeholder_contact_email_fails_regardless_of_case(): void
    {
        config(['legal.contact_email' => 'Privacy@Danang-Help.EXAMPLE']);

        $this->assertFalse(app(LegalConfigurationValidator::class)->passes());
    }

    public function test_malformed_contact_email_fails(): void
    {
        config(['legal.contact_email' => 'not-an-email']);

        $this->assertFalse(app(LegalConfigurationValidator::class)->passes());
    }

    public function test_whitespace_only_operator_name_fails(): void
    {
        config(['legal.operator_name' => '   ']);

        $this->assertFalse(app(LegalConfigurationValidator::class)->passes());
    }

    public function test_development_placeholder_operator_name_fails(): void
    {
        config(['legal.operator_name' => '本サービス運営者（名称未確定・準備中）']);

        $this->assertFalse(app(LegalConfigurationValidator::class)->passes());
    }

    public function test_draft_terms_version_fails(): void
    {
        config(['legal.terms.version' => '2026-10-draft-1']);

        $this->assertFalse(app(LegalConfigurationValidator::class)->passes());
    }

    public function test_draft_privacy_version_fails(): void
    {
        config(['legal.privacy.version' => '2026-10-draft-1']);

        $this->assertFalse(app(LegalConfigurationValidator::class)->passes());
    }

    public function test_null_terms_effective_date_fails(): void
    {
        config(['legal.terms.effective_date' => null]);

        $this->assertFalse(app(LegalConfigurationValidator::class)->passes());
    }

    public function test_null_privacy_effective_date_fails(): void
    {
        config(['legal.privacy.effective_date' => null]);

        $this->assertFalse(app(LegalConfigurationValidator::class)->passes());
    }

    public function test_problems_lists_every_unresolved_issue_at_once(): void
    {
        config([
            'legal.operator_name' => null,
            'legal.contact_email' => null,
            'legal.terms.effective_date' => null,
            'legal.privacy.effective_date' => null,
        ]);

        $problems = app(LegalConfigurationValidator::class)->problems();

        $this->assertCount(4, $problems);
    }
}
