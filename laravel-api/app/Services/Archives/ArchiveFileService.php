<?php

namespace App\Services\Archives;

use App\Models\ArchiveDocument;
use App\Models\ArchiveFileVersion;
use App\Services\Files\PrivateUploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penyimpanan file arsip privat (Fase 09a, DEC-37 D5).
 *
 * Nama file dibuat server, path/checksum tidak pernah diekspos, dan unduhan
 * memakai header pola 07c/08a tanpa Content-Disposition.
 */
class ArchiveFileService
{
    public function __construct(
        private readonly PrivateUploadValidator $validator,
    ) {}

    public function validateFile(UploadedFile $file): string
    {
        return $this->validator->validate(
            $file,
            (array) config('archives.attachment.allowed_extensions'),
            (array) config('archives.attachment.allowed_mimetypes'),
            (int) config('archives.attachment.max_file_kb'),
        );
    }

    /**
     * @return array{original_name: string, path: string, mime_type: string, size_bytes: int, checksum_sha256: string}
     */
    public function writeToDisk(ArchiveDocument $document, UploadedFile $file, string $extension): array
    {
        $diskName = (string) config('archives.attachment.disk');
        $disk = Storage::disk($diskName);

        $directory = trim((string) config('archives.attachment.path'), '/').'/'.$document->id;
        $filename = Str::uuid()->toString().'.'.$extension;

        $path = $disk->putFileAs($directory, $file, $filename);

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => ['The file could not be stored.'],
            ]);
        }

        $checksum = hash_file('sha256', $disk->path($path));

        if ($checksum === false) {
            $this->discard($path);

            throw ValidationException::withMessages([
                'file' => ['The stored file could not be verified.'],
            ]);
        }

        return [
            'original_name' => $this->validator->sanitizeName($file->getClientOriginalName()),
            'path' => $path,
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'checksum_sha256' => $checksum,
        ];
    }

    public function discard(string $path): void
    {
        try {
            Storage::disk((string) config('archives.attachment.disk'))->delete($path);
        } catch (\Throwable) {
            // Kompensasi file tidak boleh menutupi error asli.
        }
    }

    public function downloadResponse(ArchiveFileVersion $version): StreamedResponse
    {
        $disk = Storage::disk((string) config('archives.attachment.disk'));

        if (! $disk->exists($version->path)) {
            abort(response()->json(['message' => 'Archive file not found.'], 404));
        }

        $stream = $disk->readStream($version->path);

        if ($stream === null) {
            abort(response()->json(['message' => 'Archive file not found.'], 404));
        }

        return new StreamedResponse(function () use ($stream) {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $version->mime_type,
            'Content-Length' => (string) $disk->size($version->path),
            'X-Document-Filename' => $this->downloadName($version),
            'Access-Control-Expose-Headers' => 'X-Document-Filename',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadName(ArchiveFileVersion $version): string
    {
        $name = $this->validator->sanitizeName($version->original_name);

        if (pathinfo($name, PATHINFO_EXTENSION) === '') {
            $name .= '.'.$this->validator->extensionForMime($version->mime_type);
        }

        return $name;
    }
}
