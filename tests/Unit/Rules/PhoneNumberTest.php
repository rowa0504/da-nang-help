<?php

namespace Tests\Unit\Rules;

use App\Rules\PhoneNumber;
use Illuminate\Translation\PotentiallyTranslatedString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    private function passes(mixed $value): bool
    {
        $failed = false;

        (new PhoneNumber())->validate('phone', $value, function () use (&$failed) {
            $failed = true;

            return new PotentiallyTranslatedString('validation.phone_number', app('translator'));
        });

        return ! $failed;
    }

    /**
     * PhoneNumber::validate() is never invoked with null or '' in the real
     * request pipeline: 'phone' is 'nullable', and Laravel's Validator skips
     * every subsequent rule object for an attribute once it sees a null
     * value under 'nullable' (Validator::isNotNullIfMarkedAsNullable()) —
     * and the global ConvertEmptyStringsToNull middleware turns '' into null
     * before validation ever runs. That end-to-end behavior is covered by
     * RegistrationTest instead of being asserted here against the Rule in
     * isolation.
     */
    public static function validPhoneProvider(): array
    {
        return [
            'domestic format' => ['0901234567'],
            'international with spaces' => ['+84 90 123 4567'],
            'leading plus with parens and hyphens' => ['+84 (90) 123-4567'],
            'lower boundary: exactly 7 digits' => ['1234567'],
            'upper boundary: exactly 15 digits' => ['123456789012345'],
            'leading plus only' => ['+123456789'],
        ];
    }

    #[DataProvider('validPhoneProvider')]
    public function test_valid_formats_pass(string $phone): void
    {
        $this->assertTrue($this->passes($phone), "Expected '{$phone}' to pass.");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPhoneProvider(): array
    {
        return [
            'below minimum: 6 digits' => ['123456'],
            'above maximum: 16 digits' => ['1234567890123456'],
            'plus in the middle' => ['090-123-456+7'],
            'multiple plus signs' => ['++84901234567'],
            'letters' => ['090abc4567'],
            'full-width digits' => ['０９０１２３４５６７'],
            'emoji' => ["090\u{1F4DE}1234567"],
            'tab character' => ["090\t1234567"],
            'newline character' => ["090\n1234567"],
            'disallowed symbol' => ['090!1234567'],
        ];
    }

    #[DataProvider('invalidPhoneProvider')]
    public function test_invalid_formats_fail(string $phone): void
    {
        $this->assertFalse($this->passes($phone), "Expected '{$phone}' to fail.");
    }

    public function test_length_over_thirty_characters_fails(): void
    {
        // 10 digits (within the 7-15 range) padded with spaces past the
        // 30-character limit, isolating the length check from the digit
        // count and character-set checks.
        $phone = '1234567890'.str_repeat(' ', 21);

        $this->assertSame(31, strlen($phone));
        $this->assertFalse($this->passes($phone));
    }
}
