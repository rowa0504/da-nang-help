<?php

namespace Tests\Unit;

use App\Contracts\Translator;
use App\Enums\TranslationStatus;
use App\Jobs\TranslateServiceRequestJob;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestTranslation;
use App\Support\ServiceRequestHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TranslateServiceRequestJobTest extends TestCase
{
    use RefreshDatabase;

    private function pendingTranslation(ServiceRequest $serviceRequest, string $locale, string $sourceHash): ServiceRequestTranslation
    {
        return ServiceRequestTranslation::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'locale' => $locale,
            'title' => $serviceRequest->title,
            'description' => $serviceRequest->description,
            'source_hash' => $sourceHash,
            'translation_status' => TranslationStatus::Pending,
        ]);
    }

    public function test_translation_succeeds_and_marks_the_row_completed(): void
    {
        $serviceRequest = ServiceRequest::factory()->create(['title' => 'Fix my AC', 'description' => 'It leaks.']);
        $hash = ServiceRequestHasher::hash($serviceRequest->title, $serviceRequest->description);
        $this->pendingTranslation($serviceRequest, 'ja', $hash);

        app(TranslateServiceRequestJob::class, [
            'serviceRequestId' => $serviceRequest->id,
            'targetLocale' => 'ja',
            'sourceHash' => $hash,
        ])->handle(app(Translator::class));

        $translation = ServiceRequestTranslation::query()
            ->where('service_request_id', $serviceRequest->id)
            ->where('locale', 'ja')
            ->firstOrFail();

        $this->assertSame(TranslationStatus::Completed, $translation->translation_status);
        $this->assertSame('[ja] Fix my AC', $translation->title);
        $this->assertSame('[ja] It leaks.', $translation->description);
        $this->assertNotNull($translation->translated_at);
    }

    public function test_stale_hash_before_translating_is_discarded_without_calling_the_translator(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $staleHash = 'this-does-not-match-current-content';
        $this->pendingTranslation($serviceRequest, 'ja', $staleHash);

        $spy = new class implements Translator
        {
            public bool $called = false;

            public function translate(string $text, string $sourceLocale, string $targetLocale): string
            {
                $this->called = true;

                return $text;
            }
        };

        app(TranslateServiceRequestJob::class, [
            'serviceRequestId' => $serviceRequest->id,
            'targetLocale' => 'ja',
            'sourceHash' => $staleHash,
        ])->handle($spy);

        $this->assertFalse($spy->called);
        $translation = ServiceRequestTranslation::query()->where('service_request_id', $serviceRequest->id)->first();
        $this->assertSame(TranslationStatus::Pending, $translation->translation_status);
    }

    public function test_hash_mismatch_discovered_only_at_write_time_discards_the_result(): void
    {
        $serviceRequest = ServiceRequest::factory()->create(['title' => 'Original title', 'description' => 'Original description']);
        $hash = ServiceRequestHasher::hash($serviceRequest->title, $serviceRequest->description);
        $this->pendingTranslation($serviceRequest, 'ja', $hash);

        // Simulates the service request's content changing concurrently
        // while the (external) translator call is in flight.
        $racingTranslator = new class($serviceRequest) implements Translator
        {
            public function __construct(private readonly ServiceRequest $serviceRequest) {}

            public function translate(string $text, string $sourceLocale, string $targetLocale): string
            {
                $this->serviceRequest->fresh()->update(['title' => 'Changed mid-flight']);

                return "[{$targetLocale}] {$text}";
            }
        };

        app(TranslateServiceRequestJob::class, [
            'serviceRequestId' => $serviceRequest->id,
            'targetLocale' => 'ja',
            'sourceHash' => $hash,
        ])->handle($racingTranslator);

        $translation = ServiceRequestTranslation::query()->where('service_request_id', $serviceRequest->id)->first();
        $this->assertSame(TranslationStatus::Pending, $translation->translation_status);
    }

    public function test_translator_exception_marks_the_row_failed_without_throwing(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $hash = ServiceRequestHasher::hash($serviceRequest->title, $serviceRequest->description);
        $this->pendingTranslation($serviceRequest, 'ja', $hash);

        $throwing = new class implements Translator
        {
            public function translate(string $text, string $sourceLocale, string $targetLocale): string
            {
                throw new \RuntimeException('translation service unavailable');
            }
        };

        app(TranslateServiceRequestJob::class, [
            'serviceRequestId' => $serviceRequest->id,
            'targetLocale' => 'ja',
            'sourceHash' => $hash,
        ])->handle($throwing);

        $translation = ServiceRequestTranslation::query()->where('service_request_id', $serviceRequest->id)->first();
        $this->assertSame(TranslationStatus::Failed, $translation->translation_status);
    }

    public function test_the_translator_is_called_without_an_open_db_transaction(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();
        $hash = ServiceRequestHasher::hash($serviceRequest->title, $serviceRequest->description);
        $this->pendingTranslation($serviceRequest, 'ja', $hash);

        // RefreshDatabase itself wraps each test in a transaction, so the
        // baseline level here is not necessarily 0 - what matters is that
        // the Job's own DB::transaction() (around the row-locked write)
        // has not been entered yet when translate() runs, i.e. the level
        // during the call must not exceed this pre-existing baseline.
        $baselineTransactionLevel = DB::transactionLevel();

        $spy = new class implements Translator
        {
            public ?int $transactionLevelDuringCall = null;

            public function translate(string $text, string $sourceLocale, string $targetLocale): string
            {
                $this->transactionLevelDuringCall ??= DB::transactionLevel();

                return $text;
            }
        };

        app(TranslateServiceRequestJob::class, [
            'serviceRequestId' => $serviceRequest->id,
            'targetLocale' => 'ja',
            'sourceHash' => $hash,
        ])->handle($spy);

        $this->assertSame($baselineTransactionLevel, $spy->transactionLevelDuringCall);
    }

    public function test_missing_service_request_ends_the_job_without_error(): void
    {
        app(TranslateServiceRequestJob::class, [
            'serviceRequestId' => 999_999,
            'targetLocale' => 'ja',
            'sourceHash' => 'irrelevant',
        ])->handle(app(Translator::class));

        $this->assertTrue(true); // no exception was thrown
    }
}
