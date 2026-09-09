<?php

namespace App\Http\Controllers\Provider;

use App\Actions\Provider\SubmitProviderProfileAction;
use App\Exceptions\InvalidProviderVerificationTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\SubmitProviderProfileRequest;
use App\Models\Area;
use App\Models\Category;
use App\Models\ProviderProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProviderProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $this->authorize('create', ProviderProfile::class);

        $profile = $request->user()->providerProfile;
        if ($profile !== null) {
            // Unfiltered by is_active: this profile's *own* attached
            // categories/areas must keep resolving correctly for the
            // read-only summary view even after an Admin deactivates one
            // of them later (Phase 9) — the active-only 'categories'/'areas'
            // props below are for the *picker* only and must not be reused
            // to resolve names for a profile that may reference an
            // inactive one.
            $profile->load(['categories.translations', 'areas']);
        }

        return Inertia::render('Provider/Profile', [
            'profile' => $profile ? [
                'business_name' => $profile->business_name,
                'bio' => $profile->bio,
                'verification_status' => $profile->verification_status->value,
                'category_ids' => $profile->categories->pluck('id'),
                'area_ids' => $profile->areas->pluck('id'),
                'category_names' => $profile->categories->map(fn (Category $category) => $category->nameFor(app()->getLocale())),
                'area_names' => $profile->areas->pluck('name'),
                'avg_rating' => $profile->avg_rating,
                'completed_jobs_count' => $profile->completed_jobs_count,
            ] : null,
            'categories' => Category::query()
                ->activeOrdered()
                ->with('translations')
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $category->nameFor(app()->getLocale()),
                ]),
            'areas' => Area::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function submit(SubmitProviderProfileRequest $request, SubmitProviderProfileAction $action): RedirectResponse
    {
        $this->authorize('create', ProviderProfile::class);

        try {
            $action->handle($request->user(), $request->validated());
        } catch (InvalidProviderVerificationTransitionException $e) {
            return back()->withErrors(['business_name' => $e->getMessage()]);
        }

        return redirect()->route('dashboard')->with('status', __('messages.provider_profile_submitted'));
    }
}
