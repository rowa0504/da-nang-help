<?php

namespace Tests\Unit;

use App\Contracts\Translator;
use App\Enums\TranslationStatus;
use App\Jobs\TranslateOfferJob;
use App\Models\Offer;
use App\Models\OfferTranslation;
use App\Support\OfferHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TranslateOfferJobTest extends TestCase
{
    use RefreshDatabase;

    private function pendingTranslation(Offer $offer, string $locale, string $sourceHash): OfferTranslation
    {
        return OfferTranslation::factory()->create([
            'offer_id' => $offer->id,
            'locale' => $locale,
            'message' => $offer->message,
            'source_hash' => $sourceHash,
            'translation_status' => TranslationStatus::Pending,
        ]);
    }

    public function test_translation_succeeds_and_marks_the_row_completed(): void
    {
        $offer = Offer::factory()->create(['message' => 'I can fix your AC today.', 'source_locale' => 'en']);
        $hash = OfferHasher::hash($offer->message, $offer->source_locale);
        $this->pendingTranslation($offer, 'ja', $hash);

        app(TranslateOfferJob::class, [
            'offerId' => $offer->id,
            'targetLocale' => 'ja',
            'sourceHash' => $hash,
        ])->handle(app(Translator::class));

        $translation = OfferTranslation::query()
            ->where('offer_id', $offer->id)
            ->where('locale', 'ja')
            ->firstOrFail();

        $this->assertSame(TranslationStatus::Completed, $translation->translation_status);
        $this->assertSame('[ja] I can fix your AC today.', $translation->message);
        $this->assertNotNull($translation->translated_at);
    }

    public function test_stale_hash_before_translating_is_discarded_without_calling_the_translator(): void
    {
        $offer = Offer::factory()->create();
        $staleHash = 'this-does-not-match-current-content';
        $this->pendingTranslation($offer, 'ja', $staleHash);

        $spy = new class implements Translator
        {
            public bool $called = false;

            public function translate(string $text, string $sourceLocale, string $targetLocale): string
            {
                $this->called = true;

                return $text;
            }
        };

        app(TranslateOfferJob::class, [
            'offerId' => $offer->id,
            'targetLocale' => 'ja',
            'sourceHash' => $staleHash,
        ])->handle($spy);

        $this->assertFalse($spy->called);
        $translation = OfferTranslation::query()->where('offer_id', $offer->id)->first();
        $this->assertSame(TranslationStatus::Pending, $translation->translation_status);
    }

    public function test_source_locale_change_stales_an_in_flight_job_for_the_old_source(): void
    {
        // Simulates: an offer is created with source_locale=en and a job is
        // dispatched (hash computed under en). Before that job runs, the
        // provider edits the offer and changes source_locale to ja. The
        // old, now-stale job must be discarded without calling the
        // translator, because OfferHasher folds source_locale into the hash.
        $offer = Offer::factory()->create(['message' => 'Same message', 'source_locale' => 'en']);
        $oldHash = OfferHasher::hash($offer->message, 'en');
        $this->pendingTranslation($offer, 'ja', $oldHash);

        $offer->update(['source_locale' => 'ja']);

        $spy = new class implements Translator
        {
            public bool $called = false;

            public function translate(string $text, string $sourceLocale, string $targetLocale): string
            {
                $this->called = true;

                return $text;
            }
        };

        app(TranslateOfferJob::class, [
            'offerId' => $offer->id,
            'targetLocale' => 'ja',
            'sourceHash' => $oldHash,
        ])->handle($spy);

        $this->assertFalse($spy->called);
    }

    public function test_hash_mismatch_discovered_only_at_write_time_discards_the_result(): void
    {
        $offer = Offer::factory()->create(['message' => 'Original message']);
        $hash = OfferHasher::hash($offer->message, $offer->source_locale);
        $this->pendingTranslation($offer, 'ja', $hash);

        $racingTranslator = new class($offer) implements Translator
        {
            public function __construct(private readonly Offer $offer) {}

            public function translate(string $text, string $sourceLocale, string $targetLocale): string
            {
                $this->offer->fresh()->update(['message' => 'Changed mid-flight']);

                return "[{$targetLocale}] {$text}";
            }
        };

        app(TranslateOfferJob::class, [
            'offerId' => $offer->id,
            'targetLocale' => 'ja',
            'sourceHash' => $hash,
        ])->handle($racingTranslator);

        $translation = OfferTranslation::query()->where('offer_id', $offer->id)->first();
        $this->assertSame(TranslationStatus::Pending, $translation->translation_status);
    }

    public function test_translator_exception_marks_the_row_failed_without_throwing(): void
    {
        $offer = Offer::factory()->create();
        $hash = OfferHasher::hash($offer->message, $offer->source_locale);
        $this->pendingTranslation($offer, 'ja', $hash);

        $throwing = new class implements Translator
        {
            public function translate(string $text, string $sourceLocale, string $targetLocale): string
            {
                throw new \RuntimeException('translation service unavailable');
            }
        };

        app(TranslateOfferJob::class, [
            'offerId' => $offer->id,
            'targetLocale' => 'ja',
            'sourceHash' => $hash,
        ])->handle($throwing);

        $translation = OfferTranslation::query()->where('offer_id', $offer->id)->first();
        $this->assertSame(TranslationStatus::Failed, $translation->translation_status);
    }

    public function test_the_translator_is_called_without_an_open_db_transaction(): void
    {
        $offer = Offer::factory()->create();
        $hash = OfferHasher::hash($offer->message, $offer->source_locale);
        $this->pendingTranslation($offer, 'ja', $hash);

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

        app(TranslateOfferJob::class, [
            'offerId' => $offer->id,
            'targetLocale' => 'ja',
            'sourceHash' => $hash,
        ])->handle($spy);

        $this->assertSame($baselineTransactionLevel, $spy->transactionLevelDuringCall);
    }

    public function test_missing_offer_ends_the_job_without_error(): void
    {
        app(TranslateOfferJob::class, [
            'offerId' => 999_999,
            'targetLocale' => 'ja',
            'sourceHash' => 'irrelevant',
        ])->handle(app(Translator::class));

        $this->assertTrue(true);
    }
}
