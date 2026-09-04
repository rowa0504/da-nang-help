<?php

namespace App\Services\Photos;

use App\Exceptions\UnprocessablePhotoException;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Normalizes an uploaded photo for a Service Request (FR-17): validates the
 * real content type (not the extension), rejects images so large they risk
 * exhausting memory, corrects EXIF orientation, flattens transparency, and
 * always re-encodes to JPEG. Re-encoding via GD is what actually strips
 * EXIF metadata (including GPS) — GD never writes EXIF back out.
 */
class ProcessUploadedPhoto
{
    private const ALLOWED_MIME_DECODERS = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
    ];

    private const MAX_INPUT_DIMENSION_PX = 8000;

    private const MAX_INPUT_PIXELS = 13_000_000;

    private const MAX_OUTPUT_DIMENSION_PX = 2560;

    private const JPEG_QUALITY = 85;

    /**
     * @throws UnprocessablePhotoException  caller (the Action) catches this and skips just this one photo
     */
    public function handle(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new UnprocessablePhotoException("Could not read uploaded file: {$file->getClientOriginalName()}");
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $decoder = self::ALLOWED_MIME_DECODERS[$mime] ?? null;
        if ($decoder === null) {
            throw new UnprocessablePhotoException("Unsupported file type ({$mime}): {$file->getClientOriginalName()}");
        }

        $size = @getimagesize($path);
        if ($size === false) {
            throw new UnprocessablePhotoException("Could not read image dimensions: {$file->getClientOriginalName()}");
        }

        [$width, $height] = $size;
        if ($width > self::MAX_INPUT_DIMENSION_PX
            || $height > self::MAX_INPUT_DIMENSION_PX
            || ($width * $height) > self::MAX_INPUT_PIXELS) {
            throw new UnprocessablePhotoException("Image too large ({$width}x{$height}): {$file->getClientOriginalName()}");
        }

        $orientation = 1;
        if ($mime === 'image/jpeg') {
            $exif = @exif_read_data($path);
            if (is_array($exif) && isset($exif['Orientation'])) {
                $orientation = (int) $exif['Orientation'];
            }
        }

        $image = @$decoder($path);
        if (! $image instanceof GdImage) {
            throw new UnprocessablePhotoException("Could not decode image: {$file->getClientOriginalName()}");
        }

        $tmpFile = null;

        try {
            $image = $this->applyOrientation($image, $orientation);

            if ($mime !== 'image/jpeg') {
                $image = $this->flattenToWhiteBackground($image);
            }

            $image = $this->resizeToMaxDimension($image, self::MAX_OUTPUT_DIMENSION_PX);

            $tmpFile = tempnam(sys_get_temp_dir(), 'photo');
            if ($tmpFile === false || ! imagejpeg($image, $tmpFile, self::JPEG_QUALITY)) {
                throw new UnprocessablePhotoException("Could not re-encode image: {$file->getClientOriginalName()}");
            }

            $contents = file_get_contents($tmpFile);
            if ($contents === false) {
                throw new UnprocessablePhotoException("Could not read re-encoded image: {$file->getClientOriginalName()}");
            }

            $objectKey = 'service-requests/'.Str::uuid()->toString().'.jpg';

            $stored = Storage::disk(config('filesystems.default'))->put($objectKey, $contents);
            if ($stored === false) {
                throw new UnprocessablePhotoException("Could not store image: {$file->getClientOriginalName()}");
            }

            return $objectKey;
        } finally {
            imagedestroy($image);
            if (is_string($tmpFile) && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    private function applyOrientation(GdImage $image, int $orientation): GdImage
    {
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    private function flattenToWhiteBackground(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $flattened = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($flattened, 255, 255, 255);
        imagefill($flattened, 0, 0, $white);
        imagealphablending($flattened, true);
        imagecopy($flattened, $image, 0, 0, 0, 0, $width, $height);

        imagedestroy($image);

        return $flattened;
    }

    private function resizeToMaxDimension(GdImage $image, int $maxDimension): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longestSide = max($width, $height);

        if ($longestSide <= $maxDimension) {
            return $image;
        }

        $scale = $maxDimension / $longestSide;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        imagedestroy($image);

        return $resized;
    }
}
