<?php

namespace App\Core\Modules;

/** What a module declares about itself: identity, version, and what it needs from the core and other modules. */
final class ModuleManifest
{
    /** @param list<string> $dependsOn ids of modules that must be present */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $description = '',
        public readonly array $dependsOn = [],
        public readonly int $minCoreVersion = 1,
    ) {
        if (! preg_match('/^[a-z][a-z0-9-]*$/', $id)) {
            throw new \InvalidArgumentException("Module id [{$id}] must be lowercase letters, digits and dashes.");
        }
    }
}
