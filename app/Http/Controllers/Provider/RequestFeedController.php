<?php

namespace App\Http\Controllers\Provider;

use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RequestFeedController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewFeed', ServiceRequest::class);

        $profile = $request->user()->providerProfile;
        $categoryIds = $profile->categories()->pluck('categories.id');
        $areaIds = $profile->areas()->pluck('areas.id');

        $requests = ServiceRequest::query()
            ->where('status', ServiceRequestStatus::Open)
            ->where('moderation_status', ServiceRequestModerationStatus::Visible)
            ->whereIn('category_id', $categoryIds)
            ->whereIn('area_id', $areaIds)
            ->with([
                'category.translations' => fn ($query) => $query->where('locale', 'en'),
                'area',
                'photos',
                'translations',
            ])
            ->latest()
            ->paginate(20);

        return Inertia::render('Provider/Requests/Index', [
            // See ServiceRequestController::index() for why ->response()->getData(true)
            // is needed here to preserve pagination data/links/meta.
            'requests' => ServiceRequestResource::collection($requests)->response()->getData(true),
        ]);
    }
}
