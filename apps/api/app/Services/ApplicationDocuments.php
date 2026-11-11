<?php

namespace App\Services;

use App\Models\ApplicationDocument;
use App\Models\PartnerApplication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Verification documents (IDs, registrations, certificates). They are sensitive: kept on a private disk under random
 * names, only ever opened by an authorised reviewer through the API, and never given a public URL.
 */
class ApplicationDocuments
{
    public const KINDS = ['id', 'commercial_registration', 'tax_certificate', 'license'];

    /** Documents every application of this type must include. @return list<string> */
    public static function required(string $type): array
    {
        return $type === 'seller' ? ['id', 'commercial_registration', 'tax_certificate'] : ['id', 'commercial_registration'];
    }

    /** The file types accepted, by what the file really is (not what its name claims). */
    private const MIME_EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('platform.documents_disk'));
    }

    /** @throws \InvalidArgumentException when the file is not a PDF, JPEG or PNG */
    public function store(PartnerApplication $application, string $kind, UploadedFile $file): ApplicationDocument
    {
        $mime = (string) $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mime] ?? throw new \InvalidArgumentException('Only PDF, JPG or PNG files are accepted.');

        $path = $this->disk()->putFileAs("applications/{$application->id}", $file, Str::uuid().'.'.$extension);

        // A disk that fails quietly must never leave us with a record of a file that is not there.
        if (! $path) {
            throw new \RuntimeException('The document could not be stored.');
        }

        return ApplicationDocument::create([
            'application_id' => $application->id, 'kind' => $kind, 'original_name' => Str::limit(preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()), 190, ''),
            'path' => $path, 'mime' => $mime, 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()),
        ]);
    }

    public function download(ApplicationDocument $document)
    {
        return $this->disk()->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Removes the files (used when an application is withdrawn or old rejected ones are cleaned up). */
    public function purge(PartnerApplication $application): void
    {
        $this->disk()->deleteDirectory("applications/{$application->id}");
        $application->documents()->delete();
    }
}
