<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MediaLibrary;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Product photo upload for the people who may edit products (admins, store owners, sellers). */
class ProductImageController extends Controller
{
    public function store(Request $request, MediaLibrary $media)
    {
        $request->validate(['image' => 'required|file|max:'.MediaLibrary::MAX_KB]);

        try {
            $file = $media->store($request->file('image'), $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['image' => [$e->getMessage()]]);
        }
        Audit::record('media.uploaded', null, ['name' => $file->name, 'size' => $file->size, 'for' => 'product']);

        return response()->json(['path' => "/media/{$file->name}"], 201);
    }
}
