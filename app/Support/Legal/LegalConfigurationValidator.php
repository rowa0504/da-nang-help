<?php

namespace App\Support\Legal;

/**
 * Checks whether the Terms of Service / Privacy Policy are actually
 * finalized and safe to publish: a real, non-blank operator name, a real,
 * well-formed contact email (not blank, not the development placeholder —
 * matched case-insensitively — and not malformed per
 * FILTER_VALIDATE_EMAIL), and a non-draft version with a real effective
 * date for each document.
 *
 * Used by `php artisan legal:check` (a CI/CD pre-launch gate) and by
 * EnsureLegalConfigurationIsFinalized (a runtime guard on the
 * register/terms/privacy routes in production only). Deliberately not
 * wired into AppServiceProvider::boot(), which runs on every request *and*
 * every Artisan command (including `package:discover` during `composer
 * install`/image build) — a hard failure there would break the Docker
 * build itself, not just gate the legal pages.
 */
final class LegalConfigurationValidator
{
    /**
     * The exact development placeholder set in .env.example. Matched
     * literally (not just "is this blank") so a real production value can
     * never accidentally collide with it.
     */
    private const PLACEHOLDER_CONTACT_EMAIL_SUFFIX = '@danang-help.example';

    private const PLACEHOLDER_OPERATOR_NAME_MARKER = '未確定';

    /**
     * @return list<string> empty when the configuration is finalized
     */
    public function problems(): array
    {
        $problems = [];

        $operatorName = config('legal.operator_name');
        if (blank($operatorName)) {
            $problems[] = 'legal.operator_name is not set.';
        } elseif (str_contains($operatorName, self::PLACEHOLDER_OPERATOR_NAME_MARKER)) {
            $problems[] = 'legal.operator_name is still the development placeholder.';
        }

        $contactEmail = config('legal.contact_email');
        if (blank($contactEmail)) {
            $problems[] = 'legal.contact_email is not set.';
        } elseif (str_ends_with(strtolower($contactEmail), strtolower(self::PLACEHOLDER_CONTACT_EMAIL_SUFFIX))) {
            $problems[] = 'legal.contact_email is still the development placeholder.';
        } elseif (filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
            $problems[] = 'legal.contact_email is not a valid email address.';
        }

        foreach (['terms' => 'Terms', 'privacy' => 'Privacy'] as $key => $label) {
            $version = config("legal.{$key}.version");
            if (blank($version) || str_contains($version, 'draft')) {
                $problems[] = "{$label} document version [{$version}] is still a draft.";
            }

            if (config("legal.{$key}.effective_date") === null) {
                $problems[] = "{$label} document has no effective date set.";
            }
        }

        return $problems;
    }

    public function passes(): bool
    {
        return $this->problems() === [];
    }
}
