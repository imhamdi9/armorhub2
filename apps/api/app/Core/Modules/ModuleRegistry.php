<?php

namespace App\Core\Modules;

use App\Core\Core;
use App\Core\Events\EventCatalog;
use App\Core\Events\SubscriberRegistry;

/** Loads modules, checks they fit this core and each other, and lets them register in dependency order. */
class ModuleRegistry
{
    /** @var array<string, Module> */
    private array $modules = [];

    public function __construct(private EventCatalog $events, private SubscriberRegistry $subscribers)
    {
    }

    /** @param list<class-string<Module>> $classes */
    public function load(array $classes): void
    {
        $found = [];
        foreach ($classes as $class) {
            $module = app($class);
            $id = $module->manifest()->id;

            if (isset($found[$id])) {
                throw new \LogicException("Two modules use the id [{$id}].");
            }
            if ($module->manifest()->minCoreVersion > Core::VERSION) {
                throw new \LogicException("Module [{$id}] needs core version {$module->manifest()->minCoreVersion}; this core is version ".Core::VERSION.'.');
            }

            $found[$id] = $module;
        }

        foreach ($this->inDependencyOrder($found) as $module) {
            $this->modules[$module->manifest()->id] = $module;
            $module->register(new ModuleContext($module->manifest()->id, $this->events, $this->subscribers));
        }
    }

    /** @param array<string, Module> $found @return list<Module> */
    private function inDependencyOrder(array $found): array
    {
        $ordered = [];
        $visiting = [];

        $visit = function (string $id) use (&$visit, &$ordered, &$visiting, $found) {
            if (isset($ordered[$id])) {
                return;
            }
            if (isset($visiting[$id])) {
                throw new \LogicException("Modules depend on each other in a circle at [{$id}].");
            }
            $visiting[$id] = true;

            foreach ($found[$id]->manifest()->dependsOn as $dependency) {
                if (! isset($found[$dependency])) {
                    throw new \LogicException("Module [{$id}] needs module [{$dependency}], which is not installed.");
                }
                $visit($dependency);
            }

            unset($visiting[$id]);
            $ordered[$id] = $found[$id];
        };

        foreach (array_keys($found) as $id) {
            $visit($id);
        }

        return array_values($ordered);
    }

    public function get(string $id): ?Module
    {
        return $this->modules[$id] ?? null;
    }

    /** @return array<string, Module> */
    public function all(): array
    {
        return $this->modules;
    }
}
