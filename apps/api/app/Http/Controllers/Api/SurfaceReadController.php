<?php

namespace App\Http\Controllers\Api;

use App\Core\Surfaces\SurfacePublications;
use App\Core\Surfaces\SurfaceRegistry;
use App\Core\Surfaces\SurfaceStore;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

/** Reading published content. Public surfaces are open to everyone; others need a signed-in user. Never drafts. */
class SurfaceReadController extends Controller
{
    /** Scheduled changes go live the next time anyone reads content after their time (at most one check every 15 seconds). */
    private function publishDue(SurfacePublications $publications): void
    {
        if (Cache::add('surfaces.due-check', 1, 15)) {
            $publications->publishDue();
        }
    }

    public function publicSurface(string $key, SurfaceRegistry $surfaces, SurfaceStore $store, SurfacePublications $publications)
    {
        $this->publishDue($publications);
        $definition = $surfaces->find($key);
        abort_unless($definition && $definition->public, 404);

        return response()->json(['key' => $key, 'values' => $store->publishedAll($key)])->header('Cache-Control', 'public, max-age=30, s-maxage=30');
    }

    public function signedIn(string $key, SurfaceRegistry $surfaces, SurfaceStore $store, SurfacePublications $publications)
    {
        $this->publishDue($publications);
        abort_unless($surfaces->find($key), 404);

        return ['key' => $key, 'values' => $store->publishedAll($key)];
    }
}
