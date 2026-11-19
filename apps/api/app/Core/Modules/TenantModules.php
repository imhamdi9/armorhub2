<?php

namespace App\Core\Modules;

use Illuminate\Support\Facades\DB;

/** Which modules a tenant has turned off. Everything registered is on unless a tenant says otherwise. */
class TenantModules
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function enabled(?int $tenantId, string $module): bool
    {
        if ($tenantId === null) {
            return true; // platform-level event: not tied to a tenant
        }

        $key = "{$tenantId}:{$module}";

        return $this->cache[$key] ??= (bool) (DB::table('tenant_modules')->where('tenant_id', $tenantId)->where('module', $module)->value('enabled') ?? true);
    }

    public function set(int $tenantId, string $module, bool $enabled): void
    {
        DB::table('tenant_modules')->updateOrInsert(['tenant_id' => $tenantId, 'module' => $module], ['enabled' => $enabled, 'updated_at' => now(), 'created_at' => now()]);
        unset($this->cache["{$tenantId}:{$module}"]);
    }

    /** @return array<string, bool> module => enabled, for the modules a tenant has an explicit choice about */
    public function overrides(int $tenantId): array
    {
        return DB::table('tenant_modules')->where('tenant_id', $tenantId)->pluck('enabled', 'module')->map(fn ($v) => (bool) $v)->all();
    }
}
