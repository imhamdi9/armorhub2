<?php

namespace App\Modules;

use App\Modules\Contracts\IndustryModule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

class IndustryRegistry
{
    /** @var array<string, IndustryModule> */
    private array $modules = [];

    public function register(IndustryModule $module): void
    {
        $this->modules[$module->key()] = $module;
    }

    /** @return array<string, IndustryModule> */
    public function all(): array
    {
        return $this->modules;
    }

    public function has(string $key): bool
    {
        return isset($this->modules[$key]);
    }

    public function get(string $key): IndustryModule
    {
        return $this->modules[$key]
            ?? throw new InvalidArgumentException("Unknown industry module [{$key}].");
    }

    /** @return array<string, class-string> alias => class, across all modules */
    public function subjectModels(): array
    {
        return $this->collect('subjectModels');
    }

    /** @return array<string, class-string> */
    public function targetModels(): array
    {
        return $this->collect('targetModels');
    }

    /** @return array<string, class-string> */
    public function catalogModels(): array
    {
        return $this->collect('catalogModels');
    }

    /** Every alias CatalogController can serve (shared lists with an is_active flag). */
    public function sharedCatalogs(): array
    {
        return array_filter(
            $this->targetModels() + $this->catalogModels() + $this->subjectModels(),
            fn (string $class) => \Illuminate\Support\Facades\Schema::hasColumn((new $class)->getTable(), 'is_active')
        );
    }

    /** Aliases of the shared lists one module's admins manage. @return list<string> */
    public function manageableAliases(IndustryModule $module): array
    {
        $own = $module->targetModels() + $module->catalogModels() + $module->subjectModels();

        return array_values(array_intersect(array_keys($own), array_keys($this->sharedCatalogs())));
    }

    /** @return list<string> */
    public function permissions(): array
    {
        $all = [];
        foreach ($this->modules as $module) {
            array_push($all, ...$module->permissions());
        }

        return array_values(array_unique($all));
    }

    /** Register morph aliases and policies. Call once from a service provider. */
    public function boot(): void
    {
        Relation::morphMap($this->subjectModels() + $this->targetModels() + $this->catalogModels());

        foreach ($this->modules as $module) {
            foreach ($module->policies() as $model => $policy) {
                Gate::policy($model, $policy);
            }
        }
    }

    /** Load every module's API routes (call inside routes/api.php). */
    public function loadRoutes(): void
    {
        foreach ($this->modules as $module) {
            if ($file = $module->routesFile()) {
                Route::group([], $file);
            }
        }
    }

    private function collect(string $method): array
    {
        $all = [];
        foreach ($this->modules as $module) {
            $all += $module->{$method}();
        }

        return $all;
    }
}
