<?php

namespace App\Core\Events;

/**
 * The contract of one event type: its name, version, owner and payload shape.
 * Schema: field => type, where type is int|string|bool|float|array|mixed, and a trailing "?" makes it optional/nullable.
 */
final class EventDefinition
{
    private const TYPES = ['int', 'string', 'bool', 'float', 'array', 'mixed'];

    /** @param array<string, string> $schema */
    public function __construct(
        public readonly string $name,
        public readonly string $owner,
        public readonly array $schema = [],
        public readonly string $description = '',
        public readonly int $version = 1,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $name)) {
            throw new \InvalidArgumentException("Event name [{$name}] must look like domain.event_name.");
        }

        foreach ($schema as $field => $type) {
            if (! in_array(rtrim($type, '?'), self::TYPES, true)) {
                throw new \InvalidArgumentException("Event [{$name}] field [{$field}] has unknown type [{$type}].");
            }
        }
    }

    /** @throws InvalidEventPayload */
    public function validate(array $payload): void
    {
        foreach ($payload as $field => $_) {
            if (! array_key_exists($field, $this->schema)) {
                throw new InvalidEventPayload("Event [{$this->name}] has no field [{$field}].");
            }
        }

        foreach ($this->schema as $field => $type) {
            $optional = str_ends_with($type, '?');
            $base = rtrim($type, '?');

            if (! array_key_exists($field, $payload) || $payload[$field] === null) {
                if (! $optional) {
                    throw new InvalidEventPayload("Event [{$this->name}] requires field [{$field}].");
                }

                continue;
            }

            $ok = match ($base) {
                'int' => is_int($payload[$field]),
                'string' => is_string($payload[$field]),
                'bool' => is_bool($payload[$field]),
                'float' => is_int($payload[$field]) || is_float($payload[$field]),
                'array' => is_array($payload[$field]),
                default => true,
            };

            if (! $ok) {
                throw new InvalidEventPayload("Event [{$this->name}] field [{$field}] must be {$base}.");
            }
        }
    }
}
