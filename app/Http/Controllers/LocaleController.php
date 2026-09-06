<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(['en', 'ja', 'vi'])],
        ]);

        $user = $request->user();
        if ($user !== null) {
            // locale is not #[Fillable] on User, so update([...]) would be
            // silently ignored — assign directly (same pitfall found and
            // fixed for ProviderProfile.completed_jobs_count in Phase 7).
            $user->locale = $data['locale'];
            $user->save();
        }

        $request->session()->put('locale', $data['locale']);

        return back();
    }
}
