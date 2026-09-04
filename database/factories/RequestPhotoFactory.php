<?php

namespace Database\Factories;

use App\Models\RequestPhoto;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestPhoto>
 */
class RequestPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'object_key' => 'service-requests/'.fake()->uuid().'.jpg',
            'sort_order' => 0,
        ];
    }
}
