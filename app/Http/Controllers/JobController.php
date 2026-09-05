<?php

namespace App\Http\Controllers;

use App\Actions\Job\CancelJobAction;
use App\Actions\Job\ConfirmJobCompletionAction;
use App\Actions\Job\ReportJobCompletionAction;
use App\Actions\Job\StartJobAction;
use App\Enums\UserRole;
use App\Exceptions\InvalidJobTransitionException;
use App\Http\Resources\JobResource;
use App\Models\ServiceJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ServiceJob::class);

        $query = ServiceJob::query()->with(['serviceRequest', 'customer', 'provider.providerProfile']);

        if ($request->user()->role !== UserRole::Admin) {
            $query->where(function ($q) use ($request) {
                $q->where('customer_id', $request->user()->id)
                    ->orWhere('provider_id', $request->user()->id);
            });
        }

        $jobs = $query->latest()->paginate(20);

        return Inertia::render('Jobs/Index', [
            'jobs' => JobResource::collection($jobs)->response()->getData(true),
        ]);
    }

    public function show(Request $request, ServiceJob $job): Response
    {
        $this->authorize('view', $job);

        $job->load(['serviceRequest', 'customer', 'provider.providerProfile']);

        return Inertia::render('Jobs/Show', [
            'job' => (new JobResource($job))->resolve($request),
        ]);
    }

    public function start(Request $request, ServiceJob $job, StartJobAction $action): RedirectResponse
    {
        $this->authorize('start', $job);

        try {
            $action->handle($request->user(), $job);
        } catch (InvalidJobTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('jobs.show', $job);
    }

    public function reportCompletion(Request $request, ServiceJob $job, ReportJobCompletionAction $action): RedirectResponse
    {
        $this->authorize('reportCompletion', $job);

        try {
            $action->handle($request->user(), $job);
        } catch (InvalidJobTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('jobs.show', $job);
    }

    public function confirmCompletion(Request $request, ServiceJob $job, ConfirmJobCompletionAction $action): RedirectResponse
    {
        $this->authorize('confirmCompletion', $job);

        try {
            $action->handle($request->user(), $job);
        } catch (InvalidJobTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('jobs.show', $job);
    }

    public function cancel(Request $request, ServiceJob $job, CancelJobAction $action): RedirectResponse
    {
        $this->authorize('cancel', $job);

        try {
            $action->handle($request->user(), $job);
        } catch (InvalidJobTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('jobs.show', $job);
    }
}
