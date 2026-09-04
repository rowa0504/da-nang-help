<?php

namespace Tests\Unit;

use App\Support\ServiceRequestHasher;
use Tests\TestCase;

class ServiceRequestHasherTest extends TestCase
{
    public function test_same_title_and_description_produce_the_same_hash(): void
    {
        $a = ServiceRequestHasher::hash('Fix my AC', 'It is leaking water.');
        $b = ServiceRequestHasher::hash('Fix my AC', 'It is leaking water.');

        $this->assertSame($a, $b);
    }

    public function test_different_content_produces_a_different_hash(): void
    {
        $a = ServiceRequestHasher::hash('Fix my AC', 'It is leaking water.');
        $b = ServiceRequestHasher::hash('Fix my AC', 'It is leaking a lot of water.');

        $this->assertNotSame($a, $b);
    }
}
