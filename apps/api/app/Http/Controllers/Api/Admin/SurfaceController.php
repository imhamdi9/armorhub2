<?php

namespace App\Http\Controllers\Api\Admin;

use App\Core\Surfaces\SurfaceInvalid;
use App\Core\Surfaces\SurfacePublications;
use App\Core\Surfaces\SurfaceRegistry;
use App\Core\Surfaces\SurfaceStore;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Settings\Settings;
use Illuminate\Support\Carbon;
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

    /** Publishes now, or (when approval is required or a time is chosen) records a request for the Approvals list. */
    public function publish(Request $request, string $key, SurfaceRegistry $surfaces, SurfaceStore $store, SurfacePublications $publications, Settings $settings)
    {
        abort_unless($surfaces->find($key), 404);
        $data = $request->validate(['locale' => 'required|string', 'note' => 'nullable|string|max:200', 'publish_at' => 'nullable|date']);
        $needsApproval = (bool) $settings->get('content.require_approval');

        try {
            if ($needsApproval || ! empty($data['publish_at'])) {
                $p = $publications->request($key, $data['locale'], $request->user()->id, $data['note'] ?? null, $needsApproval, ! empty($data['publish_at']) ? Carbon::parse($data['publish_at']) : null);
                Audit::record('surface.publish_requested', null, ['surface' => $key, 'locale' => $data['locale'], 'status' => $p->status, 'publish_at' => $p->publish_at?->toIso8601String()]);

                return response()->json(['status' => $p->status, 'id' => $p->id, 'publish_at' => $p->publish_at?->toIso8601String()], 202);
            }

            $live = $store->publish($key, $data['locale'], null, $request->user()->id, $data['note'] ?? null);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['locale' => [$e->getMessage()]]);
        }
        Audit::record('surface.published', null, ['surface' => $key, 'locale' => $data['locale'], 'version' => $live->version, 'note' => $data['note'] ?? null]);

        return ['version' => $live->version, 'status' => 'published'];
    }

    public function publications(SurfacePublications $publications, SurfaceRegistry $surfaces, Settings $settings)
    {
        $labels = collect($surfaces->all())->mapWithKeys(fn ($s) => [$s->key => $s->label]);
        $overview = $publications->overview();
        $names = \App\Models\User::whereIn('id', collect($overview['open'])->merge($overview['recent'])->pluck('requested_by')->filter()->unique())->pluck('name', 'id');
        $decorate = fn (array $rows) => array_map(fn ($r) => $r + ['surface_label' => $labels[$r['surface_key']] ?? $r['surface_key'], 'requested_by_name' => $names[$r['requested_by']] ?? null], $rows);

        return ['require_approval' => (bool) $settings->get('content.require_approval'), 'open' => $decorate($overview['open']), 'recent' => $decorate($overview['recent'])];
    }

    public function approve(Request $request, int $id, SurfacePublications $publications)
    {
        return $this->decide($request, $id, 'approve', $publications);
    }

    public function reject(Request $request, int $id, SurfacePublications $publications)
    {
        return $this->decide($request, $id, 'reject', $publications);
    }

    public function cancel(Request $request, int $id, SurfacePublications $publications)
    {
        return $this->decide($request, $id, 'cancel', $publications);
    }

    private function decide(Request $request, int $id, string $action, SurfacePublications $publications)
    {

        try {
            $p = $publications->{$action}($id, $request->user()->id);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['request' => [$e->getMessage()]]);
        }
        Audit::record("surface.publication_{$action}", null, ['surface' => $p->surface_key, 'locale' => $p->locale, 'status' => $p->status]);

        return ['status' => $p->status];
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
