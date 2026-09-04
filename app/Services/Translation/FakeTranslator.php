<?php

namespace App\Services\Translation;

use App\Contracts\Translator;

/**
 * Local/testing-only stand-in for a real machine translation service. It
 * does not translate anything — it applies a deterministic, obviously-fake
 * transform so developers can visually confirm the translation pipeline
 * (job dispatch, hash checks, pending -> completed) is wired correctly.
 *
 * This must never be bound in a production environment: swap the
 * AppServiceProvider binding for a real Amazon Translate implementation
 * before going live (not built in Phase 4 — see the Phase 4 plan's risks
 * section). Using this in production would show users untranslated text
 * disguised as a translation.
 */
class FakeTranslator implements Translator
{
    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        return "[{$targetLocale}] {$text}";
    }
}
