<?php

namespace App\Support;

/** Fills {placeholders} in an admin-written JSON template, and flattens it for form posts. */
trait FillsTemplates
{
    /** A value that is exactly one placeholder keeps its type; placeholders inside longer text become strings. */
    protected function fill(array $template, array $values): array
    {
        return array_map(function ($node) use ($values) {
            if (is_array($node)) {
                return $this->fill($node, $values);
            }

            if (! is_string($node)) {
                return $node;
            }

            if (preg_match('/^\{(\w+)\}$/', $node, $m) && array_key_exists($m[1], $values)) {
                return $values[$m[1]];
            }

            return preg_replace_callback('/\{(\w+)\}/', fn ($m) => array_key_exists($m[1], $values) ? (string) $values[$m[1]] : $m[0], $node);
        }, $template);
    }

    /** @return array<string, scalar> */
    protected function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";
            is_array($value) ? $out += $this->flatten($value, $name) : $out[$name] = $value;
        }

        return $out;
    }
}
