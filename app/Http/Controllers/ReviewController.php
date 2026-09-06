<?php

namespace App\Http\Controllers;

use App\Actions\Review\CreateReviewAction;
use App\Exceptions\InvalidReviewTransitionException;
use App\Http\Requests\Review\CreateReviewRequest;
use App\Models\Review;
use App\Models\ServiceJob;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    public function store(CreateReviewRequest $request, ServiceJob $job, CreateReviewAction $action): RedirectResponse
    {
        $this->authorize('create', [Review::class, $job]);

        try {
            $action->handle($request->user(), $job, $request->validated());
        } catch (InvalidReviewTransitionException $e) {
            return back()->withErrors(['rating' => $e->getMessage()]);
        }

        return redirect()->route('jobs.show', $job);
    }
}
