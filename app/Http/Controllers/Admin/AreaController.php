<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateAreaAction;
use App\Actions\Admin\UpdateAreaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAreaRequest;
use App\Http\Requests\Admin\UpdateAreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Area::class);

        $areas = Area::query()->orderBy('name')->get();

        return Inertia::render('Admin/Areas/Index', [
            'areas' => $areas->map(fn (Area $area) => [
                'id' => $area->id,
                'slug' => $area->slug,
                'name' => $area->name,
                'is_active' => $area->is_active,
            ])->values(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Area::class);

        return Inertia::render('Admin/Areas/Create');
    }

    public function store(CreateAreaRequest $request, CreateAreaAction $action): RedirectResponse
    {
        $action->handle($request->validated());

        return redirect()->route('admin.areas.index')->with('status', __('messages.area_created'));
    }

    public function edit(Area $area): Response
    {
        $this->authorize('update', Area::class);

        return Inertia::render('Admin/Areas/Edit', [
            'area' => [
                'id' => $area->id,
                'slug' => $area->slug,
                'name' => $area->name,
                'is_active' => $area->is_active,
            ],
        ]);
    }

    public function update(UpdateAreaRequest $request, Area $area, UpdateAreaAction $action): RedirectResponse
    {
        $action->handle($area, $request->validated());

        return redirect()->route('admin.areas.index')->with('status', __('messages.area_updated'));
    }
}
