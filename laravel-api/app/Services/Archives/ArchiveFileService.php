<?php

namespace App\Services\Archives;

use App\Models\ArchiveDocument;
use App\Models\ArchiveFileVersion;
use App\Services\Files\PrivateUploadValidator;
use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penyimpanan file arsip privat (Fase 09a, DEC-37 D5; preview 09b, DEC-38 D2).
 *
 * Nama file dibuat server, path/checksum tidak pernah diekspos, dan unduhan
 * maupun preview memakai header pola 07c/08a tanpa Content-Disposition.
 */
class ArchiveFileService
{
    /**
     * Pesan aman untuk kegagalan integritas; tidak menyebut path/checksum.
     */
    public const INTEGRITY_MESSAGE = 'The stored file failed its integrity check.';

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

    public function assertReadable(ArchiveFileVersion $version): void
    {
        $disk = Storage::disk((string) config('archives.attachment.disk'));

        if (! $disk->exists($version->path)) {
            abort(response()->json(['message' => 'Archive file not found.'], 404));
        }

        $this->assertIntegrity($disk, $version);
    }

    public function downloadResponse(ArchiveFileVersion $version): StreamedResponse
    {
        return $this->streamResponse($version, "sandbox; default-src 'none'");
    }

    /**
     * Preview inline memakai pola header 07c/08a tanpa Content-Disposition;
     * CSP hanya sandbox agar renderer bawaan browser dapat menampilkan isi.
     */
    public function previewResponse(ArchiveFileVersion $version): StreamedResponse
    {
        return $this->streamResponse($version, 'sandbox');
    }

    private function streamResponse(ArchiveFileVersion $version, string $contentSecurityPolicy): StreamedResponse
    {
        $this->assertReadable($version);

        $disk = Storage::disk((string) config('archives.attachment.disk'));

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
            'Content-Security-Policy' => $contentSecurityPolicy,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Verifikasi SHA-256 saat baca (DEC-38 D7). Byte ditahan dan pesan aman
     * dikembalikan bila checksum tidak cocok; detail internal tidak bocor.
     */
    private function assertIntegrity(FilesystemContract $disk, ArchiveFileVersion $version): void
    {
        $checksum = hash_file('sha256', $disk->path($version->path));

        if ($checksum === false || ! hash_equals((string) $version->checksum_sha256, $checksum)) {
            abort(response()->json(['message' => self::INTEGRITY_MESSAGE], 500));
        }
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
