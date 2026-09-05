<?php

namespace App\Http\Controllers;

use App\Actions\Offer\AcceptOfferAction;
use App\Actions\Offer\CreateOfferAction;
use App\Actions\Offer\RejectOfferAction;
use App\Actions\Offer\UpdateOfferAction;
use App\Actions\Offer\WithdrawOfferAction;
use App\Exceptions\DuplicateOfferException;
use App\Exceptions\InvalidOfferTransitionException;
use App\Http\Requests\Offer\CreateOfferRequest;
use App\Http\Requests\Offer\RejectOfferRequest;
use App\Http\Requests\Offer\UpdateOfferRequest;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfferController extends Controller
{
    public function index(Request $request, ServiceRequest $serviceRequest): Response
    {
        $this->authorize('viewAny', [Offer::class, $serviceRequest]);

        $offers = Offer::where('service_request_id', $serviceRequest->id)
            ->with(['provider.providerProfile', 'translations'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Requests/Offers/Index', [
            'serviceRequest' => ['id' => $serviceRequest->id, 'title' => $serviceRequest->title],
            'offers' => OfferResource::collection($offers)->response()->getData(true),
        ]);
    }

    public function store(CreateOfferRequest $request, ServiceRequest $serviceRequest, CreateOfferAction $action): RedirectResponse
    {
        $this->authorize('create', [Offer::class, $serviceRequest]);

        try {
            $action->handle($request->user(), $serviceRequest, $request->validated());
        } catch (DuplicateOfferException $e) {
            return back()->withErrors(['price' => $e->getMessage()]);
        } catch (InvalidOfferTransitionException $e) {
            return back()->withErrors(['price' => $e->getMessage()]);
        }

        return redirect()->route('requests.show', $serviceRequest);
    }

    public function update(UpdateOfferRequest $request, Offer $offer, UpdateOfferAction $action): RedirectResponse
    {
        $this->authorize('update', $offer);

        try {
            $action->handle($request->user(), $offer, $request->validated());
        } catch (InvalidOfferTransitionException $e) {
            return back()->withErrors(['price' => $e->getMessage()]);
        }

        return redirect()->route('requests.show', $offer->serviceRequest);
    }

    public function withdraw(Request $request, Offer $offer, WithdrawOfferAction $action): RedirectResponse
    {
        $this->authorize('withdraw', $offer);

        try {
            $action->handle($request->user(), $offer);
        } catch (InvalidOfferTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('requests.show', $offer->serviceRequest);
    }

    public function accept(Request $request, Offer $offer, AcceptOfferAction $action): RedirectResponse
    {
        $this->authorize('accept', $offer);

        try {
            $action->handle($request->user(), $offer);
        } catch (InvalidOfferTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('requests.show', $offer->serviceRequest);
    }

    public function reject(RejectOfferRequest $request, Offer $offer, RejectOfferAction $action): RedirectResponse
    {
        $this->authorize('reject', $offer);

        try {
            $action->handle($request->user(), $offer);
        } catch (InvalidOfferTransitionException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('requests.offers.index', $offer->serviceRequest);
    }
}
