<?php

namespace App\Actions\Admin;

use App\Models\Area;

class UpdateAreaAction
{
    /**
     * slug is deliberately never updated here (Phase 9 plan §2#9: immutable
     * after creation — Edit forms don't even submit it).
     *
     * @param  array{name: string, is_active: bool}  $data
     */
    public function handle(Area $area, array $data): Area
    {
        $area->name = $data['name'];
        $area->is_active = $data['is_active'];
        $area->save();

        return $area;
    }
}
