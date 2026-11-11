<?php

namespace App\Http\Controllers\Api\Admin;

use App\Core\Surfaces\SurfaceInvalid;
use App\Core\Surfaces\SurfaceRegistry;
use App\Core\Surfaces\SurfaceStore;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Admins edit what modules declared editable: a working draft per language, published with a note, restorable. */
class SurfaceController extends Controller
{
    public function index(SurfaceRegistry $surfaces, SurfaceStore $store)
    {
        return ['surfaces' => collect($surfaces->all())->map(fn ($s) => $s->toArray() + [
            'published_versions' => collect(SurfaceStore::LOCALES)->mapWithKeys(fn ($l) => [$l => $store->working($s->key, $l)['published_version']]),
        ])->values()];
    }

    public function show(Request $request, string $key, SurfaceRegistry $surfaces, SurfaceStore $store)
    {
        $definition = $surfaces->find($key) ?? abort(404);
        $locale = $this->locale($request);
        $working = $store->working($key, $locale);

        return $working + [
            'surface' => $definition->toArray(), 'locale' => $locale, 'defaults' => $definition->defaults(), 'history' => $store->history($key, $locale),
        ];
    }

    public function save(Request $request, string $key, SurfaceRegistry $surfaces, SurfaceStore $store)
    {
        abort_unless($surfaces->find($key), 404);
        $data = $request->validate(['locale' => 'required|string', 'values' => 'present|array']);

        try {
            $draft = $store->saveDraft($key, $data['locale'], $data['values'], null, $request->user()->id);
        } catch (SurfaceInvalid $e) {
            throw ValidationException::withMessages(collect($e->errors)->mapWithKeys(fn ($m, $f) => ["values.{$f}" => $m])->all());
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['locale' => [$e->getMessage()]]);
        }

        return ['version' => $draft->version, 'content' => $draft->content];
    }

    public function publish(Request $request, string $key, SurfaceRegistry $surfaces, SurfaceStore $store)
    {
        abort_unless($surfaces->find($key), 404);
        $data = $request->validate(['locale' => 'required|string', 'note' => 'nullable|string|max:200']);

        try {
            $live = $store->publish($key, $data['locale'], null, $request->user()->id, $data['note'] ?? null);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['locale' => [$e->getMessage()]]);
        }
        Audit::record('surface.published', null, ['surface' => $key, 'locale' => $data['locale'], 'version' => $live->version, 'note' => $data['note'] ?? null]);

        return ['version' => $live->version];
    }

    public function rollback(Request $request, string $key, SurfaceRegistry $surfaces, SurfaceStore $store)
    {
        abort_unless($surfaces->find($key), 404);
        $data = $request->validate(['locale' => 'required|string', 'version' => 'required|integer|min:1']);

        try {
            $live = $store->rollback($key, $data['locale'], $data['version'], null, $request->user()->id);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['version' => [$e->getMessage()]]);
        }
        Audit::record('surface.rolled_back', null, ['surface' => $key, 'locale' => $data['locale'], 'restored' => $data['version'], 'version' => $live->version]);

        return ['version' => $live->version];
    }

    private function locale(Request $request): string
    {
        $locale = $request->query('locale', 'en');
        abort_unless(in_array($locale, SurfaceStore::LOCALES, true), 422);

        return $locale;
    }
}
