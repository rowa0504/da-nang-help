<?php

namespace App\Http\Controllers;

use App\Enums\ProviderVerificationStatus;
use App\Enums\ServiceJobStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\UserRole;
use App\Models\Area;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $props = [];

        if ($request->user()?->role === UserRole::Admin) {
            $props['adminStats'] = $this->adminStats();
        }

        return Inertia::render('Dashboard', $props);
    }

    /**
     * Nine simple aggregate counts (Phase 9 plan §2#8) — no joins, so no
     * N+1/performance concerns. Deliberately not a dedicated analytics
     * system (Blueprint §08 excludes that from MVP scope).
     *
     * @return array<string, int|float>
     */
    private function adminStats(): array
    {
        $totalRequests = ServiceRequest::count();
        $assignedRequests = ServiceRequest::where('status', ServiceRequestStatus::Assigned->value)->count();

        return [
            'total_requests' => $totalRequests,
            'open_requests' => ServiceRequest::where('status', ServiceRequestStatus::Open->value)->count(),
            'conversion_rate' => $totalRequests > 0 ? round($assignedRequests / $totalRequests * 100, 1) : 0.0,
            'total_jobs' => ServiceJob::count(),
            'completed_jobs' => ServiceJob::where('status', ServiceJobStatus::Completed->value)->count(),
            'pending_providers' => ProviderProfile::where('verification_status', ProviderVerificationStatus::Pending->value)->count(),
            'active_categories' => Category::where('is_active', true)->count(),
            'active_areas' => Area::where('is_active', true)->count(),
            'hidden_reviews' => Review::where('is_hidden', true)->count(),
        ];
    }
}
