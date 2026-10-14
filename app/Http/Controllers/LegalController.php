<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function terms(): Response
    {
        return Inertia::render('Terms', $this->props('terms'));
    }

    public function privacy(): Response
    {
        return Inertia::render('Privacy', $this->props('privacy'));
    }

    /**
     * operatorName/contactEmail/effectiveDate/governingLocale are always
     * sourced from config here, never hardcoded or editable from the
     * frontend — the page body (resources/js/legal/*.ts) only knows how to
     * substitute these tokens, not what their real values are.
     *
     * @return array<string, mixed>
     */
    private function props(string $document): array
    {
        return [
            'operatorName' => config('legal.operator_name'),
            'contactEmail' => config('legal.contact_email'),
            'effectiveDate' => config("legal.{$document}.effective_date"),
            'governingLocale' => config('legal.governing_locale'),
        ];
    }
}
