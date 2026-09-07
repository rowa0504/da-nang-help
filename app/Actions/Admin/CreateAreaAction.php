<?php

namespace App\Actions\Admin;

use App\Models\Area;

class CreateAreaAction
{
    /**
     * @param  array{name: string, slug: string, is_active: bool}  $data
     */
    public function handle(array $data): Area
    {
        $area = new Area();
        $area->name = $data['name'];
        $area->slug = $data['slug'];
        $area->is_active = $data['is_active'];
        $area->save();

        return $area;
    }
}
