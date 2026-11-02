<?php

namespace App\Services\Letters;

use App\Models\LetterSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penyimpanan aset privat kop surat (Fase 07c, DEC-34).
 *
 * Hanya PNG/JPEG; file selalu di-re-encode ke PNG (alpha dipertahankan),
 * nama dibuat server, dan path tidak pernah diekspos ke API.
 */
class LetterSettingsAssetService
{
    /**
     * @return array{path: string, mime: string, size_bytes: int, sha256: string}
     */
    public function store(UploadedFile $file, string $asset, int $schoolProfileId): array
    {
        $this->validateImage($file, $asset);

        $png = $this->reencodePng($file, $asset);

        $directory = trim((string) config('letters.document.assets_path'), '/')
            .'/'.$schoolProfileId;
        $path = $directory.'/'.$asset.'-'.Str::uuid()->toString().'.png';

        $disk = Storage::disk((string) config('letters.disk'));

        if (! $disk->put($path, $png)) {
            throw ValidationException::withMessages([
                $asset => ['The image could not be stored.'],
            ]);
        }

        return [
            'path' => $path,
            'mime' => 'image/png',
            'size_bytes' => strlen($png),
            'sha256' => hash('sha256', $png),
        ];
    }

    public function discard(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        try {
            Storage::disk((string) config('letters.disk'))->delete($path);
        } catch (\Throwable) {
            // Kompensasi file tidak boleh menutupi error asli.
        }
    }

    public function pathFor(LetterSettings $settings, string $asset): ?string
    {
        if (! in_array($asset, LetterSettings::ASSETS, true)) {
            return null;
        }

        $path = $settings->getAttribute($asset.'_path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Data URI untuk dirender ke PDF; path tidak pernah bocor ke HTML/API.
     */
    public function dataUri(LetterSettings $settings, string $asset): ?string
    {
        $path = $this->pathFor($settings, $asset);

        if ($path === null) {
            return null;
        }

        $disk = Storage::disk((string) config('letters.disk'));

        if (! $disk->exists($path)) {
            throw ValidationException::withMessages([
                'letter_settings' => ["The {$asset} image is missing from storage. Upload it again in Letter Settings."],
            ]);
        }

        $bytes = $disk->get($path);

        if (! is_string($bytes) || $bytes === '') {
            throw ValidationException::withMessages([
                'letter_settings' => ["The {$asset} image could not be read. Upload it again in Letter Settings."],
            ]);
        }

        $mime = $settings->getAttribute($asset.'_mime');
        $mime = is_string($mime) && in_array($mime, ['image/png', 'image/jpeg'], true)
            ? $mime
            : 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    public function stream(LetterSettings $settings, string $asset): StreamedResponse
    {
        $path = $this->pathFor($settings, $asset);

        if ($path === null) {
            abort(response()->json(['message' => 'Letter settings asset not found.'], 404));
        }

        $disk = Storage::disk((string) config('letters.disk'));

        if (! $disk->exists($path)) {
            abort(response()->json(['message' => 'Letter settings asset not found.'], 404));
        }

        $mime = $settings->getAttribute($asset.'_mime');

        return $disk->response($path, $asset.'.png', [
            'Content-Type' => is_string($mime) && $mime !== '' ? $mime : 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function validateImage(UploadedFile $file, string $asset): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                $asset => ['The image upload failed; please try again.'],
            ]);
        }

        $clientName = basename(str_replace('\\', '/', $file->getClientOriginalName()));

        if (substr_count($clientName, '.') !== 1) {
            throw ValidationException::withMessages([
                $asset => ['The file name must use a single extension.'],
            ]);
        }

        $extension = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));

        if (! in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            throw ValidationException::withMessages([
                $asset => ['Only PNG, JPG, or JPEG images are allowed.'],
            ]);
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            throw ValidationException::withMessages([
                $asset => ['The image content type is not allowed.'],
            ]);
        }

        $expectedExtension = $mime === 'image/png' ? 'png' : 'jpg';
        $isJpegAlias = $expectedExtension === 'jpg' && in_array($extension, ['jpg', 'jpeg'], true);

        if ($extension !== $expectedExtension && ! $isJpegAlias) {
            throw ValidationException::withMessages([
                $asset => ['The file extension does not match its detected content type.'],
            ]);
        }

        $size = @getimagesize($file->getRealPath());

        if ($size === false) {
            throw ValidationException::withMessages([
                $asset => ['The image file is not valid.'],
            ]);
        }

        $maxDimension = (int) config('letters.document.max_image_dimension');

        if ((int) $size[0] > $maxDimension || (int) $size[1] > $maxDimension) {
            throw ValidationException::withMessages([
                $asset => ["The image may not exceed {$maxDimension} by {$maxDimension} pixels."],
            ]);
        }

        $maxKb = (int) config('letters.document.max_image_kb');

        if (($file->getSize() ?? 0) > $maxKb * 1024) {
            throw ValidationException::withMessages([
                $asset => ["The image may not be larger than {$maxKb} KB."],
            ]);
        }
    }

    private function reencodePng(UploadedFile $file, string $asset): string
    {
        $contents = @file_get_contents($file->getRealPath());
        $image = $contents !== false ? @imagecreatefromstring($contents) : false;

        if ($image === false) {
            throw ValidationException::withMessages([
                $asset => ['The image file could not be processed.'],
            ]);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            imagedestroy($image);

            throw ValidationException::withMessages([
                $asset => ['The image file could not be processed.'],
            ]);
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();
        imagedestroy($canvas);

        if (! is_string($png) || $png === '') {
            throw ValidationException::withMessages([
                $asset => ['The image file could not be processed.'],
            ]);
        }

        return $png;
    }
}
