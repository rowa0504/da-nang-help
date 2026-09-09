<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ApproveProviderAction;
use App\Actions\Admin\RejectProviderAction;
use App\Actions\Admin\SuspendProviderAction;
use App\Exceptions\InvalidProviderVerificationTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectProviderProfileRequest;
use App\Http\Requests\Admin\SuspendProviderProfileRequest;
use App\Models\ProviderProfile;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProviderReviewController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ProviderProfile::class);

        $profiles = ProviderProfile::query()
            ->with('user:id,name,email')
            ->where('verification_status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ProviderProfile $profile) => [
                'id' => $profile->id,
                'business_name' => $profile->business_name,
                'applicant_name' => $profile->user->name,
                'applicant_email' => $profile->user->email,
                'created_at' => $profile->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Providers/Index', [
            'profiles' => $profiles,
        ]);
    }

    public function show(ProviderProfile $providerProfile): Response
    {
        $this->authorize('view', $providerProfile);

        // 'categories.translations' (unfiltered by locale) so nameFor()
        // below can resolve every category's name without an extra query
        // per category — same pattern as ServiceRequestController etc.
        $providerProfile->load(['user:id,name,email,phone', 'categories.translations', 'areas']);

        return Inertia::render('Admin/Providers/Show', [
            'profile' => [
                'id' => $providerProfile->id,
                'business_name' => $providerProfile->business_name,
                'bio' => $providerProfile->bio,
                'verification_status' => $providerProfile->verification_status->value,
                // Deliberately explicit: verification_note is #[Hidden] on
                // the model, so it must be added to this array by hand.
                // Only this Admin-facing response includes it.
                'verification_note' => $providerProfile->verification_note,
                'categories' => $providerProfile->categories->map(fn ($category) => $category->nameFor(app()->getLocale())),
                'areas' => $providerProfile->areas->pluck('name'),
                'applicant_name' => $providerProfile->user->name,
                'applicant_email' => $providerProfile->user->email,
                'applicant_phone' => $providerProfile->user->phone,
            ],
        ]);
    }

    public function approve(ProviderProfile $providerProfile, ApproveProviderAction $action): RedirectResponse
    {
        $this->authorize('approve', $providerProfile);

        try {
            $action->handle($providerProfile);
        } catch (InvalidProviderVerificationTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.providers.index')->with('status', __('messages.provider_approved'));
    }

    public function reject(RejectProviderProfileRequest $request, ProviderProfile $providerProfile, RejectProviderAction $action): RedirectResponse
    {
        $this->authorize('reject', $providerProfile);

        try {
            $action->handle($providerProfile, $request->validated('note'));
        } catch (InvalidProviderVerificationTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.providers.index')->with('status', __('messages.provider_rejected'));
    }

    public function suspend(SuspendProviderProfileRequest $request, ProviderProfile $providerProfile, SuspendProviderAction $action): RedirectResponse
    {
        $this->authorize('suspend', $providerProfile);

        try {
            $action->handle($request->user(), $providerProfile, $request->validated('note'));
        } catch (InvalidProviderVerificationTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.providers.index')->with('status', __('messages.provider_suspended'));
    }
}
