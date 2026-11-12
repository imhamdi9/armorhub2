<?php

namespace App\Services;

use App\Models\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Images admins upload for the site (hero, banner, logo). Accepted by what the file really is (never SVG: it can carry script),
 * stored under a random name on a configurable disk (local, or S3/R2 for a host with no permanent disk), and served only through
 * the API with safe headers. The site refers to them as /media/<name>.
 */
class MediaLibrary
{
    public const MAX_KB = 3072;

    private const MIME_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('platform.media_disk'));
    }

    /** @throws \InvalidArgumentException */
    public function store(UploadedFile $file, ?int $userId): MediaFile
    {
        $mime = (string) $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mime] ?? throw new \InvalidArgumentException('Only JPG, PNG, WebP or GIF images are accepted.');

        if ($file->getSize() > self::MAX_KB * 1024) {
            throw new \InvalidArgumentException('The image is larger than '.(self::MAX_KB / 1024).' MB.');
        }

        $name = Str::uuid().'.'.$extension;

        if (! $this->disk()->putFileAs('media', $file, $name)) {
            throw new \RuntimeException('The image could not be stored.');
        }

        return MediaFile::create([
            'name' => $name, 'original_name' => Str::limit(preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()), 190, ''),
            'mime' => $mime, 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'uploaded_by' => $userId,
        ]);
    }

    public function response(MediaFile $file)
    {
        return $this->disk()->response("media/{$file->name}", $file->name, [
            'Content-Type' => $file->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=86400',
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }
}
