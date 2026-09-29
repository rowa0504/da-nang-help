<?php

namespace App\Http\Controllers;

use App\Actions\ServiceRequest\CancelServiceRequestAction;
use App\Actions\ServiceRequest\CreateServiceRequestAction;
use App\Actions\ServiceRequest\UpdateServiceRequestAction;
use App\Enums\UserRole;
use App\Exceptions\InvalidServiceRequestTransitionException;
use App\Http\Requests\ServiceRequest\CancelServiceRequestRequest;
use App\Http\Requests\ServiceRequest\CreateServiceRequestRequest;
use App\Http\Requests\ServiceRequest\UpdateServiceRequestRequest;
use App\Http\Resources\JobResource;
use App\Http\Resources\OfferResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Area;
use App\Models\Category;
use App\Models\Offer;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ServiceRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ServiceRequest::class);

        $requests = ServiceRequest::query()
            ->where('customer_id', $request->user()->id)
            ->with([
                'customer',
                'category.translations',
                'area',
                'photos',
                'translations',
            ])
            ->latest()
            ->paginate(20);

        return Inertia::render('Requests/Index', [
            // ->response()->getData(true) forces the collection through
            // Laravel's paginated-response path so the prop includes
            // data/links/meta — passing the bare ResourceCollection as an
            // Inertia prop would silently drop the pagination metadata,
            // since that wrapping only happens in toResponse(), not toArray().
            'requests' => ServiceRequestResource::collection($requests)->response()->getData(true),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ServiceRequest::class);

        return Inertia::render('Requests/Create', [
            'categories' => $this->categoryOptions(),
            'areas' => $this->areaOptions(),
        ]);
    }

    public function edit(Request $request, ServiceRequest $serviceRequest): Response
    {
        $this->authorize('update', $serviceRequest);

        $serviceRequest->load('photos');

        return Inertia::render('Requests/Edit', [
            'serviceRequest' => [
                'id' => $serviceRequest->id,
                // Always the DB original — never a translated value — so
                // editing never accidentally saves translated text back as
                // the source of truth.
                'title' => $serviceRequest->title,
                'description' => $serviceRequest->description,
                'category_id' => $serviceRequest->category_id,
                'area_id' => $serviceRequest->area_id,
                'address_text' => $serviceRequest->address_text,
                'urgency' => $serviceRequest->urgency->value,
                'source_locale' => $serviceRequest->source_locale,
                'photos' => $serviceRequest->photos->map(fn ($photo) => [
                    'id' => $photo->id,
                    'url' => Storage::disk(config('filesystems.default'))->temporaryUrl($photo->object_key, now()->addMinutes(15)),
                ]),
            ],
            'categories' => $this->categoryOptions($serviceRequest->category_id),
            'areas' => $this->areaOptions($serviceRequest->area_id),
        ]);
    }

    public function store(CreateServiceRequestRequest $request, CreateServiceRequestAction $action): RedirectResponse
    {
        $this->authorize('create', ServiceRequest::class);

        $result = $action->handle(
            $request->user(),
            $request->safe()->except('photos'),
            $request->file('photos', [])
        );

        $redirect = redirect()->route('requests.show', $result->serviceRequest);
        if ($result->photoWarnings !== []) {
            $redirect->with('warning', implode(' ', $result->photoWarnings));
        }

        return $redirect;
    }

    public function show(Request $request, ServiceRequest $serviceRequest): Response
    {
        $this->authorize('view', $serviceRequest);

        $serviceRequest->load([
            'customer',
            'category.translations',
            'area',
            'photos',
            'translations',
            'serviceJob.customer',
            'serviceJob.provider.providerProfile',
        ]);

        $job = $serviceRequest->serviceJob;
        // Avoid JobResource lazy-loading a second, separate ServiceRequest
        // instance via $job->serviceRequest — point it back at the one
        // we've already loaded and populated above.
        $job?->setRelation('serviceRequest', $serviceRequest);

        $jobProp = null;
        if ($job !== null
            && ($request->user()->id === $job->customer_id
                || $request->user()->id === $job->provider_id
                || $request->user()->role === UserRole::Admin)) {
            $jobProp = (new JobResource($job))->resolve($request);
        }

        $myOffer = null;
        $canOffer = false;
        if ($request->user()->role === UserRole::Provider) {
            $offer = Offer::where('service_request_id', $serviceRequest->id)
                ->where('provider_id', $request->user()->id)
                ->with(['translations', 'provider.providerProfile'])
                ->first();
            $myOffer = $offer ? (new OfferResource($offer))->resolve($request) : null;
            $canOffer = $myOffer === null && $request->user()->can('create', [Offer::class, $serviceRequest]);

            // Lets ServiceRequestResource compute match_level for the
            // single-request page the same way RequestFeedController does,
            // so the "doesn't fully match your profile" notice on this page
            // is driven by the same data as the Feed's ranking/badges.
            $profile = $request->user()->providerProfile;
            if ($profile !== null) {
                $request->attributes->set('viewer_category_ids', $profile->categories()->pluck('categories.id')->all());
                $request->attributes->set('viewer_area_ids', $profile->areas()->pluck('areas.id')->all());
            }
        }

        return Inertia::render('Requests/Show', [
            // ->resolve($request) (a plain array) rather than the bare
            // JsonResource: Inertia auto-unwraps any Responsable prop via
            // toResponse()->getData(true), and Laravel's default single-
            // resource wrapping would nest everything under an extra
            // "data" key (request.data.title instead of request.title).
            'request' => (new ServiceRequestResource($serviceRequest))->resolve($request),
            'myOffer' => $myOffer,
            'canOffer' => $canOffer,
            'job' => $jobProp,
            'can_edit' => $request->user()->can('update', $serviceRequest),
        ]);
    }

    public function cancel(CancelServiceRequestRequest $request, ServiceRequest $serviceRequest, CancelServiceRequestAction $action): RedirectResponse
    {
        $this->authorize('cancel', $serviceRequest);

        try {
            $action->handle($request->user(), $serviceRequest);
        } catch (InvalidServiceRequestTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('requests.show', $serviceRequest);
    }

    public function update(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest, UpdateServiceRequestAction $action): RedirectResponse
    {
        $this->authorize('update', $serviceRequest);

        try {
            $result = $action->handle(
                $request->user(),
                $serviceRequest,
                $request->safe()->only(['title', 'description', 'category_id', 'area_id', 'address_text', 'urgency']),
                $request->file('photos', []),
                $request->input('remove_photo_ids', [])
            );
        } catch (InvalidServiceRequestTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        $redirect = redirect()->route('requests.show', $result->serviceRequest)->with('status', __('messages.service_request_updated'));
        if ($result->photoWarnings !== []) {
            $redirect->with('warning', implode(' ', $result->photoWarnings));
        }

        return $redirect;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, slug: string, name: string, is_inactive: bool}>
     */
    private function categoryOptions(?int $includeCurrentId = null): \Illuminate\Support\Collection
    {
        $categories = Category::query()
            ->activeOrdered()
            ->with('translations')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->nameFor(app()->getLocale()),
                'is_inactive' => false,
            ]);

        if ($includeCurrentId !== null && ! $categories->contains('id', $includeCurrentId)) {
            // The request's own current category was deactivated since it
            // was posted — still offered as an option (so re-saving without
            // touching it doesn't force a change). is_inactive is a plain
            // flag, not pre-baked text: the "no longer accepting" wording
            // lives in the frontend dictionary (resources/js/lang/*.ts,
            // requests.edit.inactive_option_suffix) via useTranslation(),
            // not Laravel's own __() — those are two separate translation
            // systems, and this string only exists in the former.
            $current = Category::query()->with('translations')->findOrFail($includeCurrentId);
            $categories->push([
                'id' => $current->id,
                'slug' => $current->slug,
                'name' => $current->nameFor(app()->getLocale()),
                'is_inactive' => true,
            ]);
        }

        return $categories;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, slug: string, is_inactive: bool}>
     */
    private function areaOptions(?int $includeCurrentId = null): \Illuminate\Support\Collection
    {
        $areas = Area::query()->active()->orderBy('name')->get(['id', 'name', 'slug']);
        $mapped = $areas->map(fn (Area $area) => ['id' => $area->id, 'name' => $area->name, 'slug' => $area->slug, 'is_inactive' => false]);

        if ($includeCurrentId !== null && ! $mapped->contains('id', $includeCurrentId)) {
            $current = Area::query()->findOrFail($includeCurrentId);
            $mapped->push([
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
                'is_inactive' => true,
            ]);
        }

        return $mapped;
    }
}
