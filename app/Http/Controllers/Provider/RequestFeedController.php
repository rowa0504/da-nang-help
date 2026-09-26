<?php

namespace App\Http\Controllers\Provider;

use App\Enums\ServiceRequestModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\RequestFeedFilterRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Area;
use App\Models\Category;
use App\Models\ServiceRequest;
use Inertia\Inertia;
use Inertia\Response;

class RequestFeedController extends Controller
{
    public function index(RequestFeedFilterRequest $request): Response
    {
        $this->authorize('viewFeed', ServiceRequest::class);

        $profile = $request->user()->providerProfile;
        $categoryIds = $profile->categories()->pluck('categories.id')->all();
        $areaIds = $profile->areas()->pluck('areas.id')->all();

        $recommendedOnly = $request->isRecommendedOnly();
        $categoryFilter = $request->categoryId();
        $areaFilter = $request->areaId();

        [$matchRankSql, $matchRankBindings] = $this->matchRankExpression($categoryIds, $areaIds);

        $query = ServiceRequest::query()
            ->where('status', ServiceRequestStatus::Open)
            ->where('moderation_status', ServiceRequestModerationStatus::Visible)
            ->with([
                'category.translations',
                'area',
                'photos',
                'translations',
            ]);

        if ($recommendedOnly) {
            // "Recommended" = anything with at least a partial match
            // (category OR area) — a full mismatch (match_level = none) is
            // excluded, but a partial match is still considered relevant.
            $query->where(function ($q) use ($categoryIds, $areaIds) {
                $q->whereIn('category_id', $categoryIds)->orWhereIn('area_id', $areaIds);
            });
        }

        if ($categoryFilter !== null) {
            $query->where('category_id', $categoryFilter);
        }

        if ($areaFilter !== null) {
            $query->where('area_id', $areaFilter);
        }

        $requests = $query
            ->orderByRaw($matchRankSql, $matchRankBindings)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Lets ServiceRequestResource compute each row's match_level from
        // the *same* id sets used for the SQL ranking above, without an
        // extra query per row (see ServiceRequestResource::toArray()).
        $request->attributes->set('viewer_category_ids', $categoryIds);
        $request->attributes->set('viewer_area_ids', $areaIds);

        return Inertia::render('Provider/Requests/Index', [
            // ->response($request)->getData(true): the explicit $request
            // matters here (unlike a bare ->response()) so
            // ServiceRequestResource::toArray() sees the same FormRequest
            // instance the viewer_category_ids/viewer_area_ids attributes
            // above were set on, rather than falling back to whatever
            // request() resolves to. See ServiceRequestController::index()
            // for why ->response(...)->getData(true) (vs a bare
            // ResourceCollection) is needed to preserve pagination
            // data/links/meta at all.
            'requests' => ServiceRequestResource::collection($requests)->response($request)->getData(true),
            'filters' => [
                'recommended' => $recommendedOnly,
                'category_id' => $categoryFilter,
                'area_id' => $areaFilter,
            ],
            'categoryOptions' => Category::query()
                ->activeOrdered()
                ->with('translations')
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $category->nameFor(app()->getLocale()),
                ]),
            'areaOptions' => Area::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Builds a `CASE WHEN ... THEN 0 WHEN ... THEN 1 ELSE 2 END` expression
     * ranking full match (both category and area) above partial (either
     * one) above no match, using bound placeholders throughout (never
     * interpolating ids into the SQL string). This ranking is used for
     * ORDER BY only — the id sets it's built from are also handed to
     * ServiceRequestResource via request attributes, which independently
     * computes the *displayed* match_level in PHP, so there is exactly one
     * source of truth for what the viewer sees even though ordering and
     * display are computed through two different mechanisms.
     *
     * @param  array<int, int>  $categoryIds
     * @param  array<int, int>  $areaIds
     * @return array{0: string, 1: array<int, int>}
     */
    private function matchRankExpression(array $categoryIds, array $areaIds): array
    {
        $category = $this->inClause('category_id', $categoryIds);
        $area = $this->inClause('area_id', $areaIds);

        $sql = "CASE
            WHEN ({$category['sql']}) AND ({$area['sql']}) THEN 0
            WHEN ({$category['sql']}) OR ({$area['sql']}) THEN 1
            ELSE 2
        END";

        $bindings = [
            ...$category['bindings'], ...$area['bindings'],
            ...$category['bindings'], ...$area['bindings'],
        ];

        return [$sql, $bindings];
    }

    /**
     * @param  array<int, int>  $values
     * @return array{sql: string, bindings: array<int, int>}
     */
    private function inClause(string $column, array $values): array
    {
        if ($values === []) {
            // An empty IN-list is invalid SQL on some drivers; "1 = 0"
            // means "never matches", the correct semantics for an empty set.
            return ['sql' => '1 = 0', 'bindings' => []];
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));

        return ['sql' => "{$column} IN ({$placeholders})", 'bindings' => $values];
    }
}
