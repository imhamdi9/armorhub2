<?php

namespace App\Services;

use App\Models\CatalogChange;
use App\Models\Order;
use App\Models\ProductCategory;
use App\Models\ProductPriceRule;
use App\Models\User;
use App\Modules\IndustryRegistry;
use App\Support\Audit;
use App\Support\MarketScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Catalog and category changes. The platform admin changes them at once; a market admin's change is only a proposal until the
 * platform admin approves it, and only then it goes live for everyone.
 */
class CatalogChanges
{
    public function __construct(private IndustryRegistry $registry)
    {
    }

    public function mustWait(User $user): bool
    {
        return ! $user->hasRole('platform-admin');
    }

    public function propose(User $by, string $target, string $action, ?int $recordId, array $payload, string $summary): CatalogChange
    {
        $change = CatalogChange::create([
            'tenant_id' => MarketScope::tenantId($by), 'requested_by' => $by->id, 'target' => $target, 'action' => $action,
            'record_id' => $recordId, 'payload' => $payload, 'summary' => $summary,
        ]);
        Audit::record('catalog.change_proposed', $change, ['target' => $target, 'action' => $action], $by);

        return $change;
    }

    public function approve(CatalogChange $c, User $by): CatalogChange
    {
        abort_unless($c->status === 'pending', 422, 'This request was already decided.');

        DB::transaction(function () use ($c) {
            $this->apply($c);
        });
        $c->update(['status' => 'approved', 'decided_by' => $by->id, 'decided_at' => now()]);
        Audit::record('catalog.change_approved', $c, [], $by);
        $this->tell($c, 'Your catalog change was approved', $c->summary.' is now live.');

        return $c;
    }

    public function reject(CatalogChange $c, User $by, string $note): CatalogChange
    {
        abort_unless($c->status === 'pending', 422, 'This request was already decided.');
        $c->update(['status' => 'rejected', 'decided_by' => $by->id, 'decided_at' => now(), 'note' => $note]);
        Audit::record('catalog.change_rejected', $c, [], $by);
        $this->tell($c, 'Your catalog change was not approved', $c->summary.': '.$note);

        return $c;
    }

    private function tell(CatalogChange $c, string $title, string $body): void
    {
        $user = User::find($c->requested_by);
        $user?->notify(new \App\Notifications\SimpleNotice($title, $body, '/dashboard/admin/catalog-changes'));
    }

    /** Performs the change for real (what the platform admin does directly, or when approving). */
    public function apply(CatalogChange $c): void
    {
        if ($c->target === 'category') {
            $this->applyCategory($c);

            return;
        }
        $alias = substr($c->target, strlen('catalog:'));
        $class = $this->registry->sharedCatalogs()[$alias] ?? abort(422, 'Unknown catalog.');

        match ($c->action) {
            'create' => $class::create($c->payload),
            'update' => $class::findOrFail($c->record_id)->update($c->payload),
            'delete' => $this->delete($class, $alias, (int) $c->record_id),
        };
    }

    private function delete(string $class, string $alias, int $id): void
    {
        $record = $class::findOrFail($id);
        if (isset($this->registry->subjectModels()[$alias]) && Order::withoutGlobalScopes()->where('subject_type', $alias)->where('subject_id', $id)->exists()) {
            throw ValidationException::withMessages(['record' => ['This item is used by existing orders and cannot be deleted.']]);
        }
        ProductPriceRule::where('target_type', $alias)->where('target_id', $id)->delete();
        $record->delete();
    }

    private function applyCategory(CatalogChange $c): void
    {
        if ($c->action === 'create') {
            if (ProductCategory::where('key', $c->payload['key'])->exists()) {
                throw ValidationException::withMessages(['key' => ['A category with this key already exists.']]);
            }
            ProductCategory::create($c->payload);

            return;
        }
        $category = ProductCategory::findOrFail($c->record_id);
        if (($c->payload['is_active'] ?? true) === false && \App\Models\Product::withoutGlobalScopes()->where('category', $category->key)->exists()) {
            throw ValidationException::withMessages(['is_active' => ['Products still use this category. Move them to another one first.']]);
        }
        $category->update($c->payload);
    }
}
