<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\HideServiceRequestAction;
use App\Exceptions\InvalidServiceRequestTransitionException;
use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestModerationController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAnyForModeration', ServiceRequest::class);

        $paginated = ServiceRequest::query()
            ->with(['customer:id,name', 'category.translations', 'area:id,name'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Requests/Index', [
            // Same hand-built {data, links, meta} shape as
            // Admin\ReviewModerationController::index(), so the frontend
            // reuses the same PaginatedData<T>/PaginationNav pattern.
            'requests' => [
                'data' => $paginated->getCollection()->map(fn (ServiceRequest $serviceRequest) => [
                    'id' => $serviceRequest->id,
                    'title' => $serviceRequest->title,
                    'customer_name' => $serviceRequest->customer->name,
                    'category_name' => $serviceRequest->category->nameFor(app()->getLocale()),
                    'area_name' => $serviceRequest->area->name,
                    'status' => $serviceRequest->status->value,
                    'moderation_status' => $serviceRequest->moderation_status->value,
                    'created_at' => $serviceRequest->created_at?->toIso8601String(),
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

    public function hide(Request $request, ServiceRequest $serviceRequest, HideServiceRequestAction $action): RedirectResponse
    {
        $this->authorize('hide', $serviceRequest);

        try {
            $action->handle($request->user(), $serviceRequest);
        } catch (InvalidServiceRequestTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('admin.requests.index')->with('status', __('messages.request_hidden'));
    }
}
