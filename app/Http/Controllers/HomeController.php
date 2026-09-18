<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            // limit(4) is applied to the query itself (not sliced from the
            // full result afterwards), so only 4 rows are ever fetched from
            // the database regardless of how many active categories exist.
            'categories' => Category::query()
                ->activeOrdered()
                ->with('translations')
                ->limit(4)
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $category->nameFor(app()->getLocale()),
                ]),
        ]);
    }
}
