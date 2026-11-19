<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Services\MediaLibrary;

/** Public images, by their random name only. */
class MediaReadController extends Controller
{
    public function show(string $name, MediaLibrary $media)
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}\.(png|jpg|webp|gif)$/', $name), 404);
        $file = MediaFile::where('name', $name)->firstOrFail();

        return $media->response($file);
    }
}
