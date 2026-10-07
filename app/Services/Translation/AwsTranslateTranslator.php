<?php

namespace App\Services\Translation;

use App\Contracts\Translator;
use App\Exceptions\Translation\PermanentTranslationException;
use App\Exceptions\Translation\RetryableTranslationException;
use Aws\Exception\CredentialsException;
use Aws\Translate\Exception\TranslateException;
use Aws\Translate\TranslateClient;
use Illuminate\Support\Facades\Log;

/**
 * Production Translator backed by Amazon Translate's synchronous
 * TranslateText API. The injected TranslateClient (see AppServiceProvider)
 * is built without an explicit 'credentials' option, so the AWS SDK's
 * default provider chain resolves them — an ECS Task Role in production,
 * or a developer's exported IAM Identity Center/STS temporary credentials
 * for local manual verification. No API key is ever read from this class
 * or from config directly.
 *
 * This class only turns an AWS-side failure into one of two domain
 * exceptions (Retryable/PermanentTranslationException); it never retries
 * anything itself. Actual retry behaviour (how many times, how long to
 * wait) lives one layer up, in TranslateServiceRequestJob/
 * TranslateOfferJob's $tries/backoff(). The TranslateClient itself also
 * retries transient errors a few times internally before an exception
 * ever reaches this class (its 'retries' => ['mode' => 'standard',
 * 'max_attempts' => 3] config in AppServiceProvider) — so a sustained
 * outage can see up to 3 (SDK-internal) x 3 (Job-level) = 9 total HTTP
 * attempts for a single translation before it is finally marked `failed`.
 * The client's http.connect_timeout/timeout are kept short specifically so
 * that 3 SDK-internal attempts can never alone exceed the Job's own 60s
 * $timeout.
 */
class AwsTranslateTranslator implements Translator
{
    /**
     * Amazon Translate's TranslateText hard limit: the Text field accepts
     * at most 10,000 UTF-8 bytes, not characters.
     * CreateServiceRequestRequest/UpdateServiceRequestRequest already
     * reject an over-limit `description` at submission time (see the
     * MaxUtf8Bytes rule) — this check is a second, defensive layer for any
     * row written before that validation existed, and for Offer `message`
     * / Service Request `title`, which have no such explicit rule (their
     * existing character limits already keep them safely under 10,000
     * bytes in practice).
     */
    private const MAX_INPUT_BYTES = 10_000;

    public function __construct(private readonly TranslateClient $client) {}

    public function translate(string $text, string $sourceLocale, string $targetLocale): string
    {
        if ($text === '') {
            return '';
        }

        $byteLength = strlen($text);
        if ($byteLength > self::MAX_INPUT_BYTES) {
            throw new PermanentTranslationException(
                "Input text is {$byteLength} bytes, exceeding Amazon Translate's ".self::MAX_INPUT_BYTES.'-byte TranslateText limit.'
            );
        }

        try {
            $result = $this->client->translateText([
                // Never 'auto': the source locale is always already known
                // here (it is the Service Request's/Offer's own
                // source_locale) and 'auto' would additionally invoke
                // Amazon Comprehend, incurring its own charge for no
                // benefit.
                'SourceLanguageCode' => $sourceLocale,
                'TargetLanguageCode' => $targetLocale,
                'Text' => $text,
            ]);
        } catch (CredentialsException $e) {
            // Thrown locally by the SDK before any request leaves the
            // process — no credentials were resolved anywhere in the
            // default provider chain. A configuration problem, not a
            // transient one: retrying will not make credentials appear.
            Log::error('Amazon Translate: no AWS credentials could be resolved.', [
                'source_locale' => $sourceLocale,
                'target_locale' => $targetLocale,
            ]);

            throw new PermanentTranslationException('AWS credentials could not be resolved: '.$e->getMessage(), previous: $e);
        } catch (TranslateException $e) {
            Log::error('Amazon Translate: TranslateText call failed.', [
                'source_locale' => $sourceLocale,
                'target_locale' => $targetLocale,
                'byte_length' => $byteLength,
                'aws_error_code' => $e->getAwsErrorCode(),
                'aws_request_id' => $e->getAwsRequestId(),
                'is_connection_error' => $e->isConnectionError(),
            ]);

            throw $this->classify($e);
        }

        return (string) ($result['TranslatedText'] ?? '');
    }

    /**
     * Agreed A-2 classification: the officially-documented permanent
     * TranslateText errors (bad input, unsupported language pair) and any
     * HTTP 403 (auth/permission/signature problems — never fixed by
     * retrying) are permanent. A connection-level failure, the documented
     * transient errors, and anything else unrecognized are retried — an
     * unclassifiable AWS-side error is safer to retry than to fail
     * outright, since the Job's own retry budget is bounded (3 attempts)
     * regardless of how it is classified.
     */
    private function classify(TranslateException $e): \RuntimeException
    {
        if ($e->isConnectionError()) {
            return new RetryableTranslationException($e->getMessage(), previous: $e);
        }

        if ($e->getStatusCode() === 403) {
            return new PermanentTranslationException($e->getMessage(), previous: $e);
        }

        $permanentCodes = [
            'TextSizeLimitExceededException',
            'InvalidRequestException',
            'UnsupportedLanguagePairException',
        ];

        if (in_array($e->getAwsErrorCode(), $permanentCodes, true)) {
            return new PermanentTranslationException($e->getMessage(), previous: $e);
        }

        // TooManyRequestsException, ServiceUnavailableException,
        // InternalServerException, and any other/unrecognized error code.
        return new RetryableTranslationException($e->getMessage(), previous: $e);
    }
}
