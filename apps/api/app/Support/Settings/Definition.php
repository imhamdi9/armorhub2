<?php

namespace App\Support\Settings;

/** Describes one admin-editable setting: how to validate, show and default it. */
final class Definition
{
    /**
     * @param  'string'|'text'|'int'|'float'|'bool'|'select'|'secret'  $type
     * @param  array<string, string>  $options  value => label, for 'select'
     */
    public function __construct(
        public readonly string $key,
        public readonly string $group,
        public readonly string $label,
        public readonly string $type,
        public readonly mixed $default = null,
        public readonly string $help = '',
        public readonly array $options = [],
        public readonly array $rules = [],
    ) {
    }

    public function isSecret(): bool
    {
        return $this->type === 'secret';
    }

    /** Laravel validation rules for an incoming value. */
    public function validationRules(): array
    {
        $base = match ($this->type) {
            'int' => ['integer'],
            'float' => ['numeric'],
            'bool' => ['boolean'],
            'select' => [\Illuminate\Validation\Rule::in(array_keys($this->options))],
            default => ['string', 'max:'.($this->type === 'text' ? 5000 : 500)],
        };

        return array_merge(['nullable'], $base, $this->rules);
    }

    /** Cast a raw (JSON-decoded or submitted) value to this setting's type. */
    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => $this->key === 'platform.currency' ? strtoupper((string) $value) : (string) $value,
            default => (string) $value,
        };
    }
}
