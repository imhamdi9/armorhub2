<?php

namespace App\Core\Surfaces;

class SurfaceRegistry
{
    /** @var array<string, SurfaceDefinition> */
    private array $surfaces = [];

    public function register(SurfaceDefinition $surface): void
    {
        if (isset($this->surfaces[$surface->key]) && $this->surfaces[$surface->key]->owner !== $surface->owner) {
            throw new \LogicException("Surface [{$surface->key}] is already owned by [{$this->surfaces[$surface->key]->owner}].");
        }

        $this->surfaces[$surface->key] = $surface;
    }

    public function find(string $key): ?SurfaceDefinition
    {
        return $this->surfaces[$key] ?? null;
    }

    /** @return array<string, SurfaceDefinition> */
    public function all(): array
    {
        ksort($this->surfaces);

        return $this->surfaces;
    }
}
