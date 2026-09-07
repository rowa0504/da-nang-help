<?php

namespace App\Actions\Admin;

use App\Exceptions\InvalidCategoryHierarchyException;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class UpdateCategoryAction
{
    /**
     * slug is deliberately never updated here (Phase 9 plan §2#9: immutable
     * after creation — Edit forms don't even submit it).
     *
     * @param  array{parent_id: ?int, is_active: bool, sort_order: int, names: array{en: string, ja: string, vi: string}}  $data
     */
    public function handle(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->id);

            if ($this->wouldCreateCycle($locked, $data['parent_id'])) {
                throw new InvalidCategoryHierarchyException(__('admin.categories.invalid_hierarchy'));
            }

            $locked->parent_id = $data['parent_id'];
            $locked->is_active = $data['is_active'];
            $locked->sort_order = $data['sort_order'];
            $locked->save();

            foreach (['en', 'ja', 'vi'] as $locale) {
                $locked->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['name' => $data['names'][$locale]]
                );
            }

            return $locked;
        });
    }

    /**
     * Edit.tsx already excludes the category itself and its descendants
     * from the parent dropdown (Admin\CategoryController::edit()), so this
     * is normally unreachable via the UI. It remains the authoritative
     * guard against a direct Action call or a stale form submission.
     * Concurrent edits by multiple Admins racing on different categories
     * in the same subtree are not fully guarded against beyond this row's
     * own lock — Blueprint assumes single-Admin operation for the MVP.
     */
    private function wouldCreateCycle(Category $category, ?int $newParentId): bool
    {
        if ($newParentId === null) {
            return false;
        }
        if ($newParentId === $category->id) {
            return true;
        }

        $current = Category::find($newParentId);
        while ($current !== null) {
            if ($current->id === $category->id) {
                return true;
            }
            $current = $current->parent_id !== null ? Category::find($current->parent_id) : null;
        }

        return false;
    }
}
