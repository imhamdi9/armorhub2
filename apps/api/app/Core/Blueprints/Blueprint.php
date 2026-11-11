<?php

namespace App\Core\Blueprints;

/**
 * A launch bundle: the steps that turn an empty platform into a working business of one kind. Pure data.
 * Each step names a registered handler and its arguments; arguments may use {{param}} and {{stepId.output}} placeholders.
 */
final class Blueprint
{
    /**
     * @param array<string, string>                                    $params field => type (string|int|bool, "?" = optional)
     * @param list<array{type: string, id?: string, args?: array}>     $steps
     */
    public function __construct(
        public readonly string $id,
        public readonly string $owner,
        public readonly string $name,
        public readonly string $description,
        public readonly array $params,
        public readonly array $steps,
        public readonly int $version = 1,
    ) {
        if (! preg_match('/^[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*)+$/', $id)) {
            throw new \InvalidArgumentException("Blueprint id [{$id}] must look like module.name.");
        }
        if ($steps === []) {
            throw new \InvalidArgumentException("Blueprint [{$id}] has no steps.");
        }
    }

    public static function fromArray(array $data, string $owner): self
    {
        return new self($data['id'] ?? '', $owner, $data['name'] ?? '', $data['description'] ?? '', $data['params'] ?? [], $data['steps'] ?? [], (int) ($data['version'] ?? 1));
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'owner' => $this->owner, 'name' => $this->name, 'description' => $this->description, 'version' => $this->version,
            'params' => $this->params, 'steps' => array_map(fn ($s) => ['id' => $s['id'] ?? null, 'type' => $s['type']], $this->steps)];
    }
}
