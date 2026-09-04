<?php

namespace App\Contracts;

interface Translator
{
    public function translate(string $text, string $sourceLocale, string $targetLocale): string;
}
