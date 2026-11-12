<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Services\MediaLibrary;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller
{
    public function index()
    {
        return ['images' => MediaFile::orderByDesc('id')->limit(60)->get(['name', 'original_name', 'size', 'created_at'])->map(fn ($m) => ['path' => "/media/{$m->name}"] + $m->only(['original_name', 'size', 'created_at']))];
    }

    public function store(Request $request, MediaLibrary $media)
    {
        $request->validate(['image' => 'required|file|max:'.MediaLibrary::MAX_KB]);

        try {
            $file = $media->store($request->file('image'), $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['image' => [$e->getMessage()]]);
        }
        Audit::record('media.uploaded', null, ['name' => $file->name, 'size' => $file->size]);

        return response()->json(['path' => "/media/{$file->name}"], 201);
    }
}
