<?php

namespace App\Console\Commands;

use App\Support\Legal\LegalConfigurationValidator;
use Illuminate\Console\Command;

/**
 * Pre-launch CI/CD gate (see RELEASE_PLAN.md's deployment pipeline): run
 * once the new Task is up but before traffic is switched to it, against
 * the production environment's actual config. A non-zero exit fails the
 * deploy before real users can see unfinalized legal text.
 */
class LegalCheckCommand extends Command
{
    protected $signature = 'legal:check';

    protected $description = 'Verify the Terms of Service / Privacy Policy configuration is finalized and safe to publish.';

    public function handle(LegalConfigurationValidator $validator): int
    {
        $problems = $validator->problems();

        if ($problems === []) {
            $this->info('Legal configuration is finalized.');

            return self::SUCCESS;
        }

        $this->error('Legal configuration is not finalized:');
        foreach ($problems as $problem) {
            $this->line("  - {$problem}");
        }

        return self::FAILURE;
    }
}
