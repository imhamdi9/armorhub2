<?php

namespace App\Core\Surfaces;

/**
 * Something admins may edit, and the rules for editing it: which fields exist, their types and limits, and whether the
 * public may read it. Declared by a module. Anything not declared is not editable: that is how security-critical areas stay locked.
 *
 * Field types: text, longtext, bool, number, color, url (https only), select, list (of items that have their own fields).
 *
 * @phpstan-type Field array{type: string, label?: string, default?: mixed, max?: int, min?: int, required?: bool, options?: list<string>, maxItems?: int, items?: array<string, array>}
 */
final class SurfaceDefinition
{
    private const TYPES = ['text', 'longtext', 'bool', 'number', 'color', 'url', 'select', 'list'];

    /** @param array<string, array> $fields */
    public function __construct(
        public readonly string $key,
        public readonly string $owner,
        public readonly string $label,
        public readonly array $fields,
        public readonly string $description = '',
        public readonly bool $public = false,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $key)) {
            throw new \InvalidArgumentException("Surface key [{$key}] must look like area.name.");
        }
        foreach ($fields as $name => $spec) {
            if (! in_array($spec['type'] ?? '', self::TYPES, true)) {
                throw new \InvalidArgumentException("Surface [{$key}] field [{$name}] has an unknown type.");
            }
        }
    }

    /** The built-in values: what shows when nothing has been published. @return array<string, mixed> */
    public function defaults(): array
    {
        return array_map(fn ($f) => $f['default'] ?? ($f['type'] === 'list' ? [] : ($f['type'] === 'bool' ? false : '')), $this->fields);
    }

    public function toArray(): array
    {
        return ['key' => $this->key, 'owner' => $this->owner, 'label' => $this->label, 'description' => $this->description, 'public' => $this->public, 'fields' => $this->fields];
    }

    /**
     * Checks a proposed set of values and returns the clean version. Only declared fields are accepted. Empty means
     * "use the built-in default", so it is dropped. Text is plain text: angle brackets are refused rather than silently changed.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     * @throws SurfaceInvalid
     */
    public function validate(array $values): array
    {
        $errors = [];
        $clean = [];

        foreach (array_diff_key($values, $this->fields) as $unknown => $_) {
            $errors[$unknown][] = 'This field does not exist.';
        }

        foreach ($this->fields as $name => $spec) {
            if (! array_key_exists($name, $values)) {
                continue;
            }

            [$value, $problems] = $this->field($spec, $values[$name]);

            foreach ($problems as $p) {
                $errors[$name][] = $p;
            }
            if (! $problems && $value !== null) {
                $clean[$name] = $value;
            }
        }

        foreach ($this->fields as $name => $spec) {
            if (($spec['required'] ?? false) && ! isset($clean[$name]) && ! isset($errors[$name])) {
                $errors[$name][] = 'This field is required.';
            }
        }

        if ($errors) {
            throw new SurfaceInvalid($errors);
        }

        return $clean;
    }

    /** @return array{0: mixed, 1: list<string>} the clean value (null = unset) and the problems */
    private function field(array $spec, mixed $value): array
    {
        $type = $spec['type'];

        if ($value === null || $value === '') {
            return [null, []];
        }

        switch ($type) {
            case 'bool':
                return is_bool($value) ? [$value, []] : [null, ['Must be true or false.']];

            case 'number':
                if (! is_numeric($value)) {
                    return [null, ['Must be a number.']];
                }
                $n = $value + 0;
                if (isset($spec['min']) && $n < $spec['min'] || isset($spec['max']) && $n > $spec['max']) {
                    return [null, ['Must be between '.($spec['min'] ?? '−∞').' and '.($spec['max'] ?? '∞').'.']];
                }

                return [$n, []];

            case 'color':
                return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? [strtolower($value), []] : [null, ['Must be a colour like #163300.']];

            case 'url':
                return is_string($value) && strlen($value) <= 500 && preg_match('~^https://[^\s<>"\']+$~i', $value) ? [$value, []] : [null, ['Must be an https:// address.']];

            case 'select':
                return in_array($value, $spec['options'] ?? [], true) ? [$value, []] : [null, ['Choose one of the listed options.']];

            case 'list':
                if (! is_array($value) || ! array_is_list($value)) {
                    return [null, ['Must be a list.']];
                }
                if (count($value) > ($spec['maxItems'] ?? 20)) {
                    return [null, ['Too many items (at most '.($spec['maxItems'] ?? 20).').']];
                }
                $items = [];
                foreach ($value as $i => $row) {
                    $row = is_array($row) ? $row : [];
                    $sub = new self($this->key.'.item', $this->owner, '', $spec['items'] ?? [], '', false);
                    try {
                        $cleanRow = $sub->validate($row);
                    } catch (SurfaceInvalid $e) {
                        return [null, ["Item ".($i + 1).': '.implode(' ', array_map(fn ($m) => implode(' ', $m), $e->errors))]];
                    }
                    if ($cleanRow !== []) {
                        $items[] = $cleanRow;
                    }
                }

                return [$items, []];

            default: // text, longtext
                if (! is_string($value)) {
                    return [null, ['Must be text.']];
                }
                if (preg_match('/[<>]/', $value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
                    return [null, ['Plain text only: no < or > characters.']];
                }
                $max = $spec['max'] ?? ($type === 'longtext' ? 2000 : 200);

                return mb_strlen($value) > $max ? [null, ["Too long (at most {$max} characters)."]] : [trim($value), []];
        }
    }
}
