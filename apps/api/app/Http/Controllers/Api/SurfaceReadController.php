<?php

namespace App\Http\Controllers\Api;

use App\Core\Surfaces\SurfaceRegistry;
use App\Core\Surfaces\SurfaceStore;
use App\Http\Controllers\Controller;

/** Reading published content. Public surfaces are open to everyone; others need a signed-in user. Never drafts. */
class SurfaceReadController extends Controller
{
    public function publicSurface(string $key, SurfaceRegistry $surfaces, SurfaceStore $store)
    {
        $definition = $surfaces->find($key);
        abort_unless($definition && $definition->public, 404);

        return response()->json(['key' => $key, 'values' => $store->publishedAll($key)])->header('Cache-Control', 'public, max-age=30, s-maxage=30');
    }

    public function signedIn(string $key, SurfaceRegistry $surfaces, SurfaceStore $store)
    {
        abort_unless($surfaces->find($key), 404);

        return ['key' => $key, 'values' => $store->publishedAll($key)];
    }
}
