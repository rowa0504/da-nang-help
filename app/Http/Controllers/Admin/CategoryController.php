<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateCategoryAction;
use App\Actions\Admin\UpdateCategoryAction;
use App\Exceptions\InvalidCategoryHierarchyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::with('translations')->get();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $this->flattenForFrontend($categories),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Category::class);

        $categories = Category::with('translations')->get();

        return Inertia::render('Admin/Categories/Create', [
            'categoryOptions' => $this->optionsForFrontend($categories),
        ]);
    }

    public function store(CreateCategoryRequest $request, CreateCategoryAction $action): RedirectResponse
    {
        $action->handle($request->validated());

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function edit(Category $category): Response
    {
        $this->authorize('update', Category::class);

        $category->load('translations');
        $all = Category::with('translations')->get();

        // Self + descendants are excluded so the dropdown never offers a
        // choice that wouldCreateCycle() would reject anyway.
        $excluded = [$category->id, ...$this->descendantIds($all, $category->id)];
        $options = $this->optionsForFrontend(
            $all->reject(fn (Category $candidate) => in_array($candidate->id, $excluded, true))
        );

        return Inertia::render('Admin/Categories/Edit', [
            'category' => [
                'id' => $category->id,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'is_active' => $category->is_active,
                'sort_order' => $category->sort_order,
                'names' => [
                    'en' => $category->translations->firstWhere('locale', 'en')?->name ?? '',
                    'ja' => $category->translations->firstWhere('locale', 'ja')?->name ?? '',
                    'vi' => $category->translations->firstWhere('locale', 'vi')?->name ?? '',
                ],
            ],
            'categoryOptions' => $options,
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategoryAction $action): RedirectResponse
    {
        try {
            $action->handle($category, $request->validated());
        } catch (InvalidCategoryHierarchyException $e) {
            return back()->withErrors(['parent_id' => $e->getMessage()]);
        }

        return redirect()->route('admin.categories.index')->with('status', 'Category updated.');
    }

    /**
     * Flattens the category tree so that every category is immediately
     * followed by its own children (recursively), rather than a simple
     * sort_order-ascending list — the latter breaks whenever a child's
     * sort_order is lower than its parent's. Siblings are ordered by
     * sort_order, then id as a tiebreaker.
     *
     * @return array<int, array{category: Category, depth: int}>
     */
    private function flattenCategoryTree(Collection $categories, ?int $parentId = null, int $depth = 0): array
    {
        $siblings = $categories
            ->where('parent_id', $parentId)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();

        $result = [];
        foreach ($siblings as $category) {
            $result[] = ['category' => $category, 'depth' => $depth];
            $result = [...$result, ...$this->flattenCategoryTree($categories, $category->id, $depth + 1)];
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    private function descendantIds(Collection $all, int $categoryId): array
    {
        $ids = [];
        $stack = [$categoryId];
        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ($all->where('parent_id', $current) as $child) {
                $ids[] = $child->id;
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    private function flattenForFrontend(Collection $categories): array
    {
        return collect($this->flattenCategoryTree($categories))
            ->map(fn (array $entry) => [
                'id' => $entry['category']->id,
                'slug' => $entry['category']->slug,
                'name' => $entry['category']->nameFor(app()->getLocale()),
                'is_active' => $entry['category']->is_active,
                'sort_order' => $entry['category']->sort_order,
                'parent_id' => $entry['category']->parent_id,
                'depth' => $entry['depth'],
            ])
            ->values()
            ->all();
    }

    private function optionsForFrontend(Collection $categories): array
    {
        return collect($this->flattenCategoryTree($categories))
            ->map(fn (array $entry) => [
                'id' => $entry['category']->id,
                'name' => $entry['category']->nameFor(app()->getLocale()),
                'depth' => $entry['depth'],
            ])
            ->values()
            ->all();
    }
}
