<?php

namespace Tests\Unit\Rules;

use App\Rules\MaxUtf8Bytes;
use Illuminate\Translation\PotentiallyTranslatedString;
use Tests\TestCase;

class MaxUtf8BytesTest extends TestCase
{
    private function passes(mixed $value, int $maxBytes = 10_000): bool
    {
        $failed = false;

        (new MaxUtf8Bytes($maxBytes))->validate('description', $value, function () use (&$failed) {
            $failed = true;

            return new PotentiallyTranslatedString('validation.max_utf8_bytes', app('translator'));
        });

        return ! $failed;
    }

    public function test_exactly_at_the_limit_passes(): void
    {
        // 10,000 single-byte ASCII characters = exactly 10,000 bytes.
        $this->assertTrue($this->passes(str_repeat('a', 10_000)));
    }

    public function test_one_byte_over_the_limit_fails(): void
    {
        $this->assertFalse($this->passes(str_repeat('a', 10_001)));
    }

    public function test_well_under_the_limit_passes(): void
    {
        $this->assertTrue($this->passes(str_repeat('a', 9_999)));
    }

    public function test_multibyte_characters_are_counted_in_bytes_not_characters(): void
    {
        // "あ" is 3 bytes in UTF-8. 3,334 copies = 10,002 bytes, i.e. over
        // the limit despite being well under 5,000 *characters* (the
        // existing max:5000 character rule would accept this value).
        $value = str_repeat('あ', 3_334);

        $this->assertSame(3_334, mb_strlen($value));
        $this->assertSame(10_002, strlen($value));
        $this->assertFalse($this->passes($value));
    }

    public function test_emoji_are_counted_in_bytes_not_characters(): void
    {
        // A basic emoji is 4 bytes in UTF-8. 2,500 copies = 10,000 bytes
        // exactly - right at the boundary, should still pass.
        $value = str_repeat('🔥', 2_500);

        $this->assertSame(10_000, strlen($value));
        $this->assertTrue($this->passes($value));

        $this->assertFalse($this->passes($value.'x'));
    }

    public function test_non_string_value_is_ignored_and_left_to_other_rules(): void
    {
        $this->assertTrue($this->passes(null));
        $this->assertTrue($this->passes(123));
    }

    public function test_custom_max_bytes_is_respected(): void
    {
        $this->assertTrue($this->passes(str_repeat('a', 50), maxBytes: 50));
        $this->assertFalse($this->passes(str_repeat('a', 51), maxBytes: 50));
    }
}
