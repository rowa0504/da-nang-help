<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Terms of Service / Privacy Policy
    |--------------------------------------------------------------------------
    |
    | version and effective_date are committed to source control, not read
    | from the environment: bumping a legal document is a content/code
    | change (this file), not a per-environment setting. While a document
    | is still a draft, its effective_date stays null and the page does not
    | display an effective-date line at all; version keeps the literal
    | string "draft" until the document is finalized. See
    | App\Support\Legal\LegalConfigurationValidator, which treats a null
    | effective_date or a "draft" version as not production-ready.
    |
    | governing_locale is the locale whose law governs these documents and
    | whose text is authoritative if translations conflict (see each
    | document's final section).
    |
    */

    'terms' => [
        'version' => '2026-10-draft-1',
        'effective_date' => null,
    ],

    'privacy' => [
        'version' => '2026-10-draft-1',
        'effective_date' => null,
    ],

    'governing_locale' => 'vi',

    /*
    |--------------------------------------------------------------------------
    | Operator identity
    |--------------------------------------------------------------------------
    |
    | Unlike the above, these genuinely vary per deployment and are never
    | committed with a real value. No fallback default is given on purpose:
    | every environment, including local, must set these explicitly (see
    | .env.example for the development placeholders). Production must
    | override them with real values before launch — see
    | LegalConfigurationValidator, LegalCheckCommand (`php artisan
    | legal:check`), and the EnsureLegalConfigurationIsFinalized middleware.
    |
    */

    'operator_name' => env('LEGAL_OPERATOR_NAME'),
    'contact_email' => env('LEGAL_CONTACT_EMAIL'),

];
