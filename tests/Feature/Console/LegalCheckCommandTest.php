<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

class LegalCheckCommandTest extends TestCase
{
    public function test_fails_while_the_committed_defaults_are_still_draft(): void
    {
        // phpunit.xml sets a real operator_name/contact_email, but
        // config/legal.php's committed terms/privacy version and
        // effective_date are still the draft defaults.
        $this->artisan('legal:check')->assertExitCode(1);
    }

    public function test_succeeds_once_finalized(): void
    {
        config([
            'legal.terms.version' => '2027-01-01-final',
            'legal.terms.effective_date' => '2027-01-01',
            'legal.privacy.version' => '2027-01-01-final',
            'legal.privacy.effective_date' => '2027-01-01',
        ]);

        $this->artisan('legal:check')->assertExitCode(0);
    }
}
