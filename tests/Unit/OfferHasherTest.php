<?php

namespace Tests\Unit;

use App\Support\OfferHasher;
use Tests\TestCase;

class OfferHasherTest extends TestCase
{
    public function test_same_message_and_source_locale_produce_the_same_hash(): void
    {
        $a = OfferHasher::hash('I can fix this today.', 'en');
        $b = OfferHasher::hash('I can fix this today.', 'en');

        $this->assertSame($a, $b);
    }

    public function test_different_message_produces_a_different_hash(): void
    {
        $a = OfferHasher::hash('I can fix this today.', 'en');
        $b = OfferHasher::hash('I can fix this tomorrow.', 'en');

        $this->assertNotSame($a, $b);
    }

    public function test_different_source_locale_alone_produces_a_different_hash(): void
    {
        $a = OfferHasher::hash('I can fix this today.', 'en');
        $b = OfferHasher::hash('I can fix this today.', 'ja');

        $this->assertNotSame($a, $b);
    }
}
