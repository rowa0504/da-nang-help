<?php

namespace App\Http\Controllers;

use App\Actions\ServiceRequest\CancelServiceRequestAction;
use App\Actions\ServiceRequest\CreateServiceRequestAction;
use App\Enums\UserRole;
use App\Exceptions\InvalidServiceRequestTransitionException;
use App\Http\Requests\ServiceRequest\CancelServiceRequestRequest;
use App\Http\Requests\ServiceRequest\CreateServiceRequestRequest;
use App\Http\Resources\OfferResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Area;
use App\Models\Category;
use App\Models\Offer;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'category.translations' => fn ($query) => $query->where('locale', 'en'),
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
            'categories' => Category::query()
                ->activeOrdered()
                ->with(['translations' => fn ($query) => $query->where('locale', 'en')])
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $category->translations->first()?->name ?? $category->slug,
                ]),
            'areas' => Area::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
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
            'category.translations' => fn ($query) => $query->where('locale', 'en'),
            'area',
            'photos',
            'translations',
        ]);

        $myOffer = null;
        $canOffer = false;
        if ($request->user()->role === UserRole::Provider) {
            $offer = Offer::where('service_request_id', $serviceRequest->id)
                ->where('provider_id', $request->user()->id)
                ->with(['translations', 'provider.providerProfile'])
                ->first();
            $myOffer = $offer ? (new OfferResource($offer))->resolve($request) : null;
            $canOffer = $myOffer === null && $request->user()->can('create', [Offer::class, $serviceRequest]);
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
}
