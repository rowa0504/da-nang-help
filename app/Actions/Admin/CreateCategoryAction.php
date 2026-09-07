<?php

namespace App\Actions\Admin;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CreateCategoryAction
{
    /**
     * @param  array{slug: string, parent_id: ?int, is_active: bool, sort_order: int, names: array{en: string, ja: string, vi: string}}  $data
     */
    public function handle(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            $category = new Category();
            $category->slug = $data['slug'];
            $category->parent_id = $data['parent_id'];
            $category->is_active = $data['is_active'];
            $category->sort_order = $data['sort_order'];
            $category->save();

            foreach (['en', 'ja', 'vi'] as $locale) {
                $category->translations()->create([
                    'locale' => $locale,
                    'name' => $data['names'][$locale],
                ]);
            }

            return $category;
        });
    }
}
