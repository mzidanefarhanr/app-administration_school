<?php

namespace App\Services\Letters;

use App\Models\Letter;
use App\Models\LetterAttachment;
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
    /**
     * Validasi keamanan file dan kembalikan ekstensi simpan berdasarkan MIME server.
     */
    public function validateFile(UploadedFile $file): string
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['The file upload failed; please try again.'],
            ]);
        }

        $clientName = $this->sanitizeName($file->getClientOriginalName());

        if (substr_count($clientName, '.') !== 1) {
            throw ValidationException::withMessages([
                'file' => ['The file name must use a single extension.'],
            ]);
        }

        $extension = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));

        if (! in_array($extension, config('letters.allowed_extensions'), true)) {
            throw ValidationException::withMessages([
                'file' => ['Only PDF, JPG, JPEG, or PNG files are allowed.'],
            ]);
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, config('letters.allowed_mimetypes'), true)) {
            throw ValidationException::withMessages([
                'file' => ['The file content type is not allowed.'],
            ]);
        }

        $expectedExtension = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ][$mime];

        $isJpegAlias = $expectedExtension === 'jpg' && in_array($extension, ['jpg', 'jpeg'], true);

        if ($extension !== $expectedExtension && ! $isJpegAlias) {
            throw ValidationException::withMessages([
                'file' => ['The file extension does not match its detected content type.'],
            ]);
        }

        if (in_array($mime, ['image/jpeg', 'image/png'], true)
            && @getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages([
                'file' => ['The image file is not valid.'],
            ]);
        }

        $maxBytes = ((int) config('letters.max_file_kb')) * 1024;

        if (($file->getSize() ?? 0) > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => ['The file may not be larger than 10 MB.'],
            ]);
        }

        return $expectedExtension;
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
            $extension = [
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
            ][$attachment->mime_type] ?? 'bin';

            $name .= '.'.$extension;
        }

        return $name;
    }

    public function sanitizeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F"]/u', '', $name) ?? '';
        $name = trim($name);

        return $name !== '' ? Str::limit($name, 255, '') : 'attachment';
    }
}
