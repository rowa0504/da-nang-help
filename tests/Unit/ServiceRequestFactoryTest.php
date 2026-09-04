<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_specifying_a_customer_does_not_create_an_extra_user(): void
    {
        $customer = User::factory()->create();
        $countBeforeServiceRequest = User::count();

        ServiceRequest::factory()->forCustomer($customer)->create();

        $this->assertSame($countBeforeServiceRequest, User::count());
    }

    public function test_specified_customer_id_is_used(): void
    {
        $customer = User::factory()->create();

        $serviceRequest = ServiceRequest::factory()->forCustomer($customer)->create();

        $this->assertSame($customer->id, $serviceRequest->customer_id);
    }

    public function test_unspecified_customer_creates_exactly_one_customer_role_user(): void
    {
        $countBefore = User::count();

        $serviceRequest = ServiceRequest::factory()->create();

        $this->assertSame($countBefore + 1, User::count());
        $this->assertSame(UserRole::Customer, $serviceRequest->customer->role);
    }
}
