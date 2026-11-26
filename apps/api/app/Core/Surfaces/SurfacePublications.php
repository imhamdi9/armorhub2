<?php

namespace App\Core\Surfaces;

use App\Core\Surfaces\Models\SurfacePublication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Governed publishing: a draft can wait for a second person's approval and/or for a chosen time before going live. A request is
 * tied to the exact draft it was made for; if the draft is edited afterwards the request goes stale instead of publishing
 * something nobody approved. Due requests are published whenever the site next reads content, so no scheduler is needed
 * (the `surfaces:publish-due` command does the same, for hosts that have one).
 */
class SurfacePublications
{
    public function __construct(private SurfaceStore $store)
    {
    }

    /** @throws \DomainException */
    public function request(string $key, string $locale, int $by, ?string $note, bool $needsApproval, ?Carbon $publishAt): SurfacePublication
    {
        $hash = $this->store->draftHash($key, $locale) ?? throw new \DomainException('There is no draft to publish.');

        if ($publishAt && $publishAt->lte(now()->addMinute()->subSeconds(5))) {
            throw new \DomainException('Choose a time at least one minute from now.');
        }

        return DB::transaction(function () use ($key, $locale, $by, $note, $needsApproval, $publishAt, $hash) {
            // A newer request replaces an older one still waiting for the same content.
            SurfacePublication::where('surface_key', $key)->where('locale', $locale)->whereIn('status', ['pending', 'scheduled'])
                ->update(['status' => 'cancelled', 'decided_by' => $by, 'decided_at' => now()]);

            return SurfacePublication::create([
                'surface_key' => $key, 'locale' => $locale, 'content_hash' => $hash, 'note' => $note ? mb_substr($note, 0, 200) : null,
                'publish_at' => $publishAt, 'requested_by' => $by, 'status' => $needsApproval ? 'pending' : 'scheduled',
            ]);
        });
    }

    /** @throws \DomainException */
    public function approve(int $id, int $approver): SurfacePublication
    {
        $p = $this->open($id, ['pending']);

        if ($p->requested_by === $approver) {
            throw new \DomainException('Somebody else must approve your change.');
        }

        $p->update(['decided_by' => $approver, 'decided_at' => now()]);

        if ($p->publish_at && $p->publish_at->isFuture()) {
            $p->update(['status' => 'scheduled']);

            return $p->fresh();
        }

        return $this->publish($p, $approver);
    }

    public function reject(int $id, int $by): SurfacePublication
    {
        $p = $this->open($id, ['pending']);
        $p->update(['status' => 'rejected', 'decided_by' => $by, 'decided_at' => now()]);

        return $p;
    }

    public function cancel(int $id, int $by): SurfacePublication
    {
        $p = $this->open($id, ['pending', 'scheduled']);
        $p->update(['status' => 'cancelled', 'decided_by' => $by, 'decided_at' => now()]);

        return $p;
    }

    /** Publishes every scheduled request that is due. Safe to call often and from several places at once. @return int how many went live */
    public function publishDue(): int
    {
        $done = 0;

        foreach (SurfacePublication::where('status', 'scheduled')->where('publish_at', '<=', now())->orderBy('publish_at')->pluck('id') as $id) {
            $claimed = SurfacePublication::whereKey($id)->where('status', 'scheduled')->update(['status' => 'publishing']);   // only one caller wins

            if (! $claimed) {
                continue;
            }

            try {
                if ($this->publish(SurfacePublication::findOrFail($id), null)->status === 'published') {
                    $done++;
                }
            } catch (\Throwable $e) {
                SurfacePublication::whereKey($id)->update(['status' => 'failed']);
                report($e);
            }
        }

        return $done;
    }

    /** @return array{open: list<array<string, mixed>>, recent: list<array<string, mixed>>} */
    public function overview(): array
    {
        $rows = fn ($q) => $q->get()->map(fn (SurfacePublication $p) => $p->toArray())->all();

        return [
            'open' => $rows(SurfacePublication::whereIn('status', ['pending', 'scheduled'])->orderBy('publish_at')->orderBy('id')),
            'recent' => $rows(SurfacePublication::whereNotIn('status', ['pending', 'scheduled'])->orderByDesc('id')->limit(15)),
        ];
    }

    private function publish(SurfacePublication $p, ?int $by): SurfacePublication
    {
        if ($this->store->draftHash($p->surface_key, $p->locale) !== $p->content_hash) {
            $p->update(['status' => 'stale', 'decided_at' => now()]);

            return $p;
        }

        $live = $this->store->publish($p->surface_key, $p->locale, null, $by ?? $p->decided_by ?? $p->requested_by, $p->note);
        $p->update(['status' => 'published', 'published_version' => $live->version, 'decided_at' => $p->decided_at ?? now()]);

        return $p;
    }

    /** @param list<string> $statuses @throws \DomainException */
    private function open(int $id, array $statuses): SurfacePublication
    {
        $p = SurfacePublication::find($id) ?? throw new \DomainException('That request no longer exists.');

        if (! in_array($p->status, $statuses, true)) {
            throw new \DomainException("This request is already {$p->status}.");
        }

        return $p;
    }
}
