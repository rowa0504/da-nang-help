<?php

namespace Tests\Unit;

use App\Exceptions\UnprocessablePhotoException;
use App\Services\Photos\ProcessUploadedPhoto;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessUploadedPhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ProcessUploadedPhoto writes through Storage::disk(config('filesystems.default')),
        // which without faking would write real files under storage/app/private
        // on every run (RefreshDatabase only resets the DB, not the filesystem).
        Storage::fake(config('filesystems.default'));
    }

    private function jpegFile(int $width, int $height, string $name = 'photo.jpg'): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 100, 150, 200));

        $path = tempnam(sys_get_temp_dir(), 'test-photo');
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    private function transparentPngFile(int $width = 60, int $height = 40): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        $opaque = imagecolorallocate($image, 200, 50, 50);
        imagefilledrectangle($image, 5, 5, $width - 5, $height - 5, $opaque);

        $path = tempnam(sys_get_temp_dir(), 'test-photo');
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'transparent.png', 'image/png', null, true);
    }

    /**
     * Hand-builds a minimal JPEG carrying only an EXIF Orientation tag
     * (no GPS), for testing that ProcessUploadedPhoto corrects orientation.
     */
    private function jpegWithOrientation(int $width, int $height, int $orientation): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 80, 160, 90));
        $basePath = tempnam(sys_get_temp_dir(), 'test-photo-base');
        imagejpeg($image, $basePath, 90);
        imagedestroy($image);
        $jpegBytes = file_get_contents($basePath);
        @unlink($basePath);

        $tiffHeader = 'II'.pack('v', 42).pack('V', 8);
        $ifd0 = pack('v', 1);
        $ifd0 .= pack('vvV', 0x0112, 3, 1).pack('v', $orientation)."\0\0";
        $ifd0 .= pack('V', 0);
        $tiff = $tiffHeader.$ifd0;
        $exifPayload = "Exif\0\0".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($exifPayload) + 2).$exifPayload;

        $outBytes = substr($jpegBytes, 0, 2).$app1.substr($jpegBytes, 2);
        $outPath = tempnam(sys_get_temp_dir(), 'test-photo-oriented');
        file_put_contents($outPath, $outBytes);

        return new UploadedFile($outPath, 'oriented.jpg', 'image/jpeg', null, true);
    }

    private function outputDimensions(string $objectKey): array
    {
        $path = Storage::disk(config('filesystems.default'))->path($objectKey);

        return getimagesize($path);
    }

    public function test_gps_exif_is_stripped_from_the_output(): void
    {
        $fixture = base_path('tests/Fixtures/photo-with-gps-exif.jpg');
        $file = new UploadedFile($fixture, 'gps.jpg', 'image/jpeg', null, true);

        $objectKey = app(ProcessUploadedPhoto::class)->handle($file);

        $outputPath = Storage::disk(config('filesystems.default'))->path($objectKey);
        $exif = @exif_read_data($outputPath);

        $this->assertTrue($exif === false || ! isset($exif['GPSLatitude']));
    }

    public function test_non_image_file_is_rejected(): void
    {
        $fake = UploadedFile::fake()->create('not-a-photo.jpg', 10, 'application/pdf');

        $this->expectException(UnprocessablePhotoException::class);

        app(ProcessUploadedPhoto::class)->handle($fake);
    }

    public function test_typical_smartphone_photo_dimensions_are_allowed(): void
    {
        $file = $this->jpegFile(4032, 3024);

        $objectKey = app(ProcessUploadedPhoto::class)->handle($file);

        $this->assertStringEndsWith('.jpg', $objectKey);
    }

    public function test_image_exceeding_the_max_side_is_rejected(): void
    {
        $file = $this->jpegFile(8500, 100);

        $this->expectException(UnprocessablePhotoException::class);

        app(ProcessUploadedPhoto::class)->handle($file);
    }

    public function test_image_exceeding_the_max_total_pixels_is_rejected_even_within_side_limit(): void
    {
        $file = $this->jpegFile(4200, 3700); // 15,540,000 px, each side within 8000 but total exceeds 13,000,000

        $this->expectException(UnprocessablePhotoException::class);

        app(ProcessUploadedPhoto::class)->handle($file);
    }

    public function test_output_is_downscaled_to_the_max_output_dimension(): void
    {
        $file = $this->jpegFile(4000, 2000);

        $objectKey = app(ProcessUploadedPhoto::class)->handle($file);

        [$width, $height] = $this->outputDimensions($objectKey);
        $this->assertLessThanOrEqual(2560, max($width, $height));
        // Aspect ratio preserved (2:1)
        $this->assertEqualsWithDelta(2.0, $width / $height, 0.05);
    }

    public function test_output_is_always_jpeg(): void
    {
        $file = $this->transparentPngFile();

        $objectKey = app(ProcessUploadedPhoto::class)->handle($file);

        $this->assertStringEndsWith('.jpg', $objectKey);
        [, , $type] = $this->outputDimensions($objectKey);
        $this->assertSame(IMAGETYPE_JPEG, $type);
    }

    public function test_transparent_png_is_flattened_onto_a_white_background(): void
    {
        $file = $this->transparentPngFile(60, 40);

        $objectKey = app(ProcessUploadedPhoto::class)->handle($file);

        $path = Storage::disk(config('filesystems.default'))->path($objectKey);
        $decoded = imagecreatefromjpeg($path);
        $this->assertInstanceOf(GdImage::class, $decoded);
        // A corner pixel (outside the filled rectangle) should now be white,
        // not transparent (JPEG has no alpha channel to begin with, but this
        // confirms the flatten step ran rather than producing black/garbage).
        $rgb = imagecolorat($decoded, 1, 1);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        imagedestroy($decoded);
        $this->assertGreaterThan(240, $r);
        $this->assertGreaterThan(240, $g);
        $this->assertGreaterThan(240, $b);
    }

    public function test_orientation_6_swaps_width_and_height(): void
    {
        $file = $this->jpegWithOrientation(60, 40, 6);

        $objectKey = app(ProcessUploadedPhoto::class)->handle($file);

        [$width, $height] = $this->outputDimensions($objectKey);
        $this->assertGreaterThan($width, $height); // 60x40 source -> 40x60 after a 90-degree correction
    }

    public function test_storage_put_returning_false_is_treated_as_a_failure(): void
    {
        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('put')->andReturn(false);

        $file = $this->jpegFile(100, 100);

        $this->expectException(UnprocessablePhotoException::class);

        app(ProcessUploadedPhoto::class)->handle($file);
    }
}
