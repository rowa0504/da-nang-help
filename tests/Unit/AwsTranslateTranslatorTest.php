<?php

namespace Tests\Unit;

use App\Exceptions\Translation\PermanentTranslationException;
use App\Exceptions\Translation\RetryableTranslationException;
use App\Services\Translation\AwsTranslateTranslator;
use Aws\Command;
use Aws\Exception\CredentialsException;
use Aws\Result;
use Aws\Translate\Exception\TranslateException;
use Aws\Translate\TranslateClient;
use GuzzleHttp\Psr7\Response;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AwsTranslateTranslatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function makeTranslateException(
        ?string $code,
        bool $connectionError = false,
        ?int $statusCode = null,
    ): TranslateException {
        $context = [
            'code' => $code,
            'request_id' => 'req-test-123',
            'connection_error' => $connectionError,
        ];

        if ($statusCode !== null) {
            $context['response'] = new Response($statusCode);
        }

        return new TranslateException('simulated AWS error', new Command('TranslateText'), $context);
    }

    public function test_empty_string_returns_empty_string_without_calling_the_client(): void
    {
        $client = Mockery::mock(TranslateClient::class);
        $client->shouldNotReceive('translateText');

        $translator = new AwsTranslateTranslator($client);

        $this->assertSame('', $translator->translate('', 'en', 'ja'));
    }

    public function test_input_over_ten_thousand_bytes_is_rejected_before_calling_the_client(): void
    {
        $client = Mockery::mock(TranslateClient::class);
        $client->shouldNotReceive('translateText');

        $translator = new AwsTranslateTranslator($client);

        // 10,001 single-byte ASCII characters = 10,001 bytes.
        $oversized = str_repeat('a', 10_001);

        $this->expectException(PermanentTranslationException::class);

        $translator->translate($oversized, 'en', 'ja');
    }

    public function test_exactly_ten_thousand_bytes_is_sent_to_the_client(): void
    {
        $text = str_repeat('a', 10_000);

        $client = Mockery::mock(TranslateClient::class);
        $client->shouldReceive('translateText')
            ->once()
            ->with([
                'SourceLanguageCode' => 'en',
                'TargetLanguageCode' => 'ja',
                'Text' => $text,
            ])
            ->andReturn(new Result(['TranslatedText' => 'translated']));

        $translator = new AwsTranslateTranslator($client);

        $this->assertSame('translated', $translator->translate($text, 'en', 'ja'));
    }

    public function test_successful_call_never_passes_auto_as_the_source_language(): void
    {
        $capturedArgs = null;

        $client = Mockery::mock(TranslateClient::class);
        $client->shouldReceive('translateText')
            ->once()
            ->andReturnUsing(function (array $args) use (&$capturedArgs) {
                $capturedArgs = $args;

                return new Result(['TranslatedText' => 'translated']);
            });

        $translator = new AwsTranslateTranslator($client);

        $translator->translate('hello', 'en', 'ja');

        $this->assertSame('en', $capturedArgs['SourceLanguageCode']);
        $this->assertNotSame('auto', $capturedArgs['SourceLanguageCode']);
    }

    public static function permanentErrorCodeProvider(): array
    {
        return [
            'TextSizeLimitExceededException' => ['TextSizeLimitExceededException'],
            'InvalidRequestException' => ['InvalidRequestException'],
            'UnsupportedLanguagePairException' => ['UnsupportedLanguagePairException'],
        ];
    }

    #[DataProvider('permanentErrorCodeProvider')]
    public function test_documented_permanent_error_codes_are_not_retried(string $code): void
    {
        $client = Mockery::mock(TranslateClient::class);
        $client->shouldReceive('translateText')->once()->andThrow($this->makeTranslateException($code));

        $translator = new AwsTranslateTranslator($client);

        $this->expectException(PermanentTranslationException::class);

        $translator->translate('hello', 'en', 'ja');
    }

    public static function transientErrorCodeProvider(): array
    {
        return [
            'TooManyRequestsException' => ['TooManyRequestsException'],
            'ServiceUnavailableException' => ['ServiceUnavailableException'],
            'InternalServerException' => ['InternalServerException'],
        ];
    }

    #[DataProvider('transientErrorCodeProvider')]
    public function test_documented_transient_error_codes_are_retried(string $code): void
    {
        $client = Mockery::mock(TranslateClient::class);
        $client->shouldReceive('translateText')->once()->andThrow($this->makeTranslateException($code));

        $translator = new AwsTranslateTranslator($client);

        $this->expectException(RetryableTranslationException::class);

        $translator->translate('hello', 'en', 'ja');
    }

    public function test_a_connection_error_is_retried_regardless_of_error_code(): void
    {
        $client = Mockery::mock(TranslateClient::class);
        // No AWS error code at all is available for a pure connection
        // failure (DNS, timeout, refused) - isConnectionError() is the
        // only signal.
        $client->shouldReceive('translateText')->once()->andThrow(
            $this->makeTranslateException(null, connectionError: true)
        );

        $translator = new AwsTranslateTranslator($client);

        $this->expectException(RetryableTranslationException::class);

        $translator->translate('hello', 'en', 'ja');
    }

    public function test_an_http_403_is_treated_as_a_permanent_auth_or_permission_error(): void
    {
        $client = Mockery::mock(TranslateClient::class);
        // A code that is not in our documented lists at all - the 403
        // status is what must drive the classification here, covering
        // generic AWS API-gateway-level auth rejections (AccessDenied,
        // UnrecognizedClientException, InvalidSignatureException, ...)
        // that are not part of TranslateText's own documented error set.
        $client->shouldReceive('translateText')->once()->andThrow(
            $this->makeTranslateException('AccessDeniedException', statusCode: 403)
        );

        $translator = new AwsTranslateTranslator($client);

        $this->expectException(PermanentTranslationException::class);

        $translator->translate('hello', 'en', 'ja');
    }

    public function test_an_unrecognized_error_code_is_retried_rather_than_failed_outright(): void
    {
        $client = Mockery::mock(TranslateClient::class);
        $client->shouldReceive('translateText')->once()->andThrow(
            $this->makeTranslateException('SomeFutureErrorTypeNotYetDocumented')
        );

        $translator = new AwsTranslateTranslator($client);

        $this->expectException(RetryableTranslationException::class);

        $translator->translate('hello', 'en', 'ja');
    }

    public function test_missing_aws_credentials_is_a_permanent_configuration_error(): void
    {
        $client = Mockery::mock(TranslateClient::class);
        $client->shouldReceive('translateText')->once()->andThrow(
            new CredentialsException('no credentials found in the default provider chain')
        );

        $translator = new AwsTranslateTranslator($client);

        $this->expectException(PermanentTranslationException::class);

        $translator->translate('hello', 'en', 'ja');
    }

    public function test_translated_text_missing_from_the_result_returns_an_empty_string(): void
    {
        $client = Mockery::mock(TranslateClient::class);
        $client->shouldReceive('translateText')->once()->andReturn(new Result([]));

        $translator = new AwsTranslateTranslator($client);

        $this->assertSame('', $translator->translate('hello', 'en', 'ja'));
    }
}
