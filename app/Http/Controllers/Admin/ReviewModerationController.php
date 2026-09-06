<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\HideReviewAction;
use App\Exceptions\InvalidReviewTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewModerationController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Review::class);

        $paginated = Review::query()
            ->with(['rater:id,name', 'ratee:id,name'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Reviews/Index', [
            // Built by hand (rather than a JsonResource collection) to match
            // the same {data, links, meta} shape as OfferResource::collection()
            // ->response()->getData(true) elsewhere, so the frontend can reuse
            // the same PaginatedData<T>/PaginationNav pattern (see
            // Requests/Offers/Index.tsx). A bare paginator's own toArray()
            // is a flatter, differently-shaped structure and would not match.
            'reviews' => [
                'data' => $paginated->getCollection()->map(fn (Review $review) => [
                    'id' => $review->id,
                    'job_id' => $review->job_id,
                    'rater_name' => $review->rater->name,
                    'ratee_name' => $review->ratee->name,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'is_hidden' => $review->is_hidden,
                    'created_at' => $review->created_at?->toIso8601String(),
                ])->values(),
                'links' => [
                    'first' => $paginated->url(1),
                    'last' => $paginated->url($paginated->lastPage()),
                    'prev' => $paginated->previousPageUrl(),
                    'next' => $paginated->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'from' => $paginated->firstItem(),
                    'last_page' => $paginated->lastPage(),
                    'links' => $paginated->linkCollection()->toArray(),
                    'path' => $paginated->path(),
                    'per_page' => $paginated->perPage(),
                    'to' => $paginated->lastItem(),
                    'total' => $paginated->total(),
                ],
            ],
        ]);
    }

    public function hide(Request $request, Review $review, HideReviewAction $action): RedirectResponse
    {
        $this->authorize('hide', $review);

        try {
            $action->handle($request->user(), $review);
        } catch (InvalidReviewTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.reviews.index')->with('status', 'Review hidden.');
    }
}
