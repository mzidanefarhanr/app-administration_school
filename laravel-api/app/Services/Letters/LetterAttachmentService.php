<?php

namespace App\Services\Letters;

use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Services\Files\PrivateUploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penyimpanan lampiran privat surat (Fase 07a, DEC-32).
 *
 * Nama file dibuat server, MIME dideteksi dari isi file, dan path tidak pernah
 * diekspos ke API. File tidak dihapus saat baris di-soft-delete (DEC-13).
 */
class LetterAttachmentService
{
    public function __construct(
        private readonly PrivateUploadValidator $validator,
    ) {}

    /**
     * Validasi keamanan file dan kembalikan ekstensi simpan berdasarkan MIME server.
     */
    public function validateFile(UploadedFile $file): string
    {
        return $this->validator->validate(
            $file,
            (array) config('letters.allowed_extensions'),
            (array) config('letters.allowed_mimetypes'),
            (int) config('letters.max_file_kb'),
        );
    }

    /**
     * Tulis file ke disk privat dengan nama acak.
     *
     * @return array{original_name: string, path: string, mime_type: string, size_bytes: int, checksum_sha256: string}
     */
    public function writeToDisk(Letter $letter, UploadedFile $file, string $extension): array
    {
        $diskName = (string) config('letters.disk');
        $disk = Storage::disk($diskName);

        $directory = trim((string) config('letters.attachments_path'), '/').'/'.$letter->id;
        $filename = Str::uuid()->toString().'.'.$extension;

        $path = $disk->putFileAs($directory, $file, $filename);

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => ['The file could not be stored.'],
            ]);
        }

        $absolutePath = $disk->path($path);
        $checksum = hash_file('sha256', $absolutePath);

        if ($checksum === false) {
            $this->discard($path);

            throw ValidationException::withMessages([
                'file' => ['The stored file could not be verified.'],
            ]);
        }

        return [
            'original_name' => $this->sanitizeName($file->getClientOriginalName()),
            'path' => $path,
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'checksum_sha256' => $checksum,
        ];
    }

    /**
     * Hapus file baru secara best effort saat transaksi DB gagal.
     */
    public function discard(string $path): void
    {
        try {
            Storage::disk((string) config('letters.disk'))->delete($path);
        } catch (\Throwable) {
            // Kompensasi file tidak boleh menutupi error asli.
        }
    }

    public function delete(LetterAttachment $attachment): void
    {
        $attachment->delete();
    }

    /**
     * Stream unduhan berizin dengan header keamanan; file tetap di disk.
     */
    public function downloadResponse(LetterAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk((string) config('letters.disk'));

        if (! $disk->exists($attachment->path)) {
            abort(response()->json(['message' => 'Attachment file not found.'], 404));
        }

        return $disk->download($attachment->path, $this->downloadName($attachment), [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadName(LetterAttachment $attachment): string
    {
        $name = $this->sanitizeName($attachment->original_name);

        if (pathinfo($name, PATHINFO_EXTENSION) === '') {
            $name .= '.'.$this->validator->extensionForMime($attachment->mime_type);
        }

        return $name;
    }

    public function sanitizeName(string $name): string
    {
        return $this->validator->sanitizeName($name);
    }
}
