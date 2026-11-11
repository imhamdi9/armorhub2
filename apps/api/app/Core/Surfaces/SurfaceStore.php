<?php

namespace App\Core\Surfaces;

use App\Core\Events\Event;
use App\Core\Events\EventBus;
use App\Core\Surfaces\Models\SurfaceVersion;
use Illuminate\Support\Facades\DB;

/**
 * Reads and changes surface content with a draft, publish and rollback discipline. Visitors always see the last published
 * version over the built-in defaults; an admin's work in progress never leaks until it is published; every version is kept.
 */
class SurfaceStore
{
    public const LOCALES = ['en', 'ar'];

    public function __construct(private SurfaceRegistry $registry, private EventBus $events)
    {
    }

    private function definition(string $key): SurfaceDefinition
    {
        return $this->registry->find($key) ?? throw new \DomainException("Surface [{$key}] is not editable.");
    }

    /** What visitors see: built-in defaults overlaid with the published content for this language. @return array<string, mixed> */
    public function published(string $key, string $locale = 'en', ?int $tenantId = null): array
    {
        $definition = $this->definition($key);
        $row = $this->latest($key, $locale, $tenantId, 'published');

        return array_replace($definition->defaults(), $row?->content ?? []);
    }

    /** Published content in every language, for pages that switch language in the browser. @return array<string, array<string, mixed>> */
    public function publishedAll(string $key, ?int $tenantId = null): array
    {
        return collect(self::LOCALES)->mapWithKeys(fn ($l) => [$l => $this->published($key, $l, $tenantId)])->all();
    }

    /** The admin's working copy: the draft if there is one, otherwise what is published. */
    public function working(string $key, string $locale, ?int $tenantId = null): array
    {
        $draft = $this->latest($key, $locale, $tenantId, 'draft');
        $published = $this->latest($key, $locale, $tenantId, 'published');

        return ['draft' => $draft?->content, 'published' => $published?->content ?? [], 'has_draft' => (bool) $draft, 'published_version' => $published?->version];
    }

    /** @param array<string, mixed> $values @throws SurfaceInvalid */
    public function saveDraft(string $key, string $locale, array $values, ?int $tenantId = null, ?int $by = null): SurfaceVersion
    {
        $this->assertLocale($locale);
        $clean = $this->definition($key)->validate($values);

        return DB::transaction(function () use ($key, $locale, $tenantId, $by, $clean) {
            $draft = $this->latest($key, $locale, $tenantId, 'draft');

            if ($draft) {
                $draft->update(['content' => $clean, 'created_by' => $by]);

                return $draft;
            }

            return $this->add($key, $locale, $tenantId, $clean, 'draft', $by);
        });
    }

    /** Makes the draft live. The previous published version is kept (archived), so it can be restored. */
    public function publish(string $key, string $locale, ?int $tenantId = null, ?int $by = null, ?string $note = null): SurfaceVersion
    {
        $this->assertLocale($locale);

        return DB::transaction(function () use ($key, $locale, $tenantId, $by, $note) {
            $draft = $this->latest($key, $locale, $tenantId, 'draft') ?? throw new \DomainException('There is no draft to publish.');

            return $this->goLive($draft, $key, $locale, $tenantId, $by, $note);
        });
    }

    /** Puts an earlier version back live as a new version (history is never rewritten). */
    public function rollback(string $key, string $locale, int $version, ?int $tenantId = null, ?int $by = null): SurfaceVersion
    {
        $this->assertLocale($locale);
        $old = $this->scope($key, $locale, $tenantId)->where('version', $version)->whereIn('status', ['published', 'archived'])->first()
            ?? throw new \DomainException('That version cannot be restored.');

        return DB::transaction(function () use ($old, $key, $locale, $tenantId, $by) {
            $copy = $this->add($key, $locale, $tenantId, $old->content, 'draft', $by);

            return $this->goLive($copy, $key, $locale, $tenantId, $by, "Restored version {$old->version}");
        });
    }

    /** @return list<array{version: int, status: string, note: ?string, published_at: mixed, created_by: ?int}> */
    public function history(string $key, string $locale, ?int $tenantId = null): array
    {
        return $this->scope($key, $locale, $tenantId)->whereIn('status', ['published', 'archived'])->orderByDesc('version')->limit(30)
            ->get(['version', 'status', 'note', 'published_at', 'created_by'])->toArray();
    }

    private function goLive(SurfaceVersion $version, string $key, string $locale, ?int $tenantId, ?int $by, ?string $note): SurfaceVersion
    {
        $this->scope($key, $locale, $tenantId)->where('status', 'published')->update(['status' => 'archived']);
        $version->update(['status' => 'published', 'published_at' => now(), 'note' => $note ? mb_substr($note, 0, 200) : null, 'created_by' => $by ?? $version->created_by]);

        $this->events->publish(new Event('surface.published', ['surface' => $key, 'version' => $version->version, 'locale' => $locale, 'tenant_id' => $tenantId],
            $tenantId, 'surface', $key, $by ? 'user' : null, $by));

        return $version;
    }

    private function add(string $key, string $locale, ?int $tenantId, array $content, string $status, ?int $by): SurfaceVersion
    {
        $next = (int) $this->scope($key, $locale, $tenantId)->max('version') + 1;

        return SurfaceVersion::create(['surface_key' => $key, 'tenant_id' => $tenantId, 'locale' => $locale, 'version' => $next, 'content' => $content, 'status' => $status, 'created_by' => $by]);
    }

    private function latest(string $key, string $locale, ?int $tenantId, string $status): ?SurfaceVersion
    {
        return $this->scope($key, $locale, $tenantId)->where('status', $status)->orderByDesc('version')->first();
    }

    private function scope(string $key, string $locale, ?int $tenantId)
    {
        return SurfaceVersion::where('surface_key', $key)->where('locale', $locale)->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId));
    }

    private function assertLocale(string $locale): void
    {
        if (! in_array($locale, self::LOCALES, true)) {
            throw new \DomainException("Unknown language [{$locale}].");
        }
    }
}
