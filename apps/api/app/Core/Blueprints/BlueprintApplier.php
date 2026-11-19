<?php

namespace App\Core\Blueprints;

use App\Core\Blueprints\Models\BlueprintApplication;
use App\Core\Events\Event;
use App\Core\Events\EventBus;
use Illuminate\Support\Facades\DB;

/**
 * Applies a blueprint: checks the operator's answers, runs the steps in order inside one transaction, and records
 * what was created. Applying the same blueprint with the same answers twice returns the first result.
 */
class BlueprintApplier
{
    public function __construct(private BlueprintRegistry $registry, private EventBus $events)
    {
    }

    /** @param array<string, mixed> $params @throws BlueprintError */
    public function apply(string $id, array $params, ?int $actorId = null): BlueprintApplication
    {
        $blueprint = $this->registry->get($id) ?? throw new BlueprintError("Blueprint [{$id}] does not exist.");
        $params = $this->cleanParams($blueprint, $params);
        $hash = hash('sha256', json_encode($params));

        if ($existing = BlueprintApplication::where('blueprint_id', $id)->where('params_hash', $hash)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($blueprint, $params, $hash, $actorId) {
            $context = new BlueprintContext($params, $actorId);

            foreach ($blueprint->steps as $index => $step) {
                $handler = $this->registry->handler($step['type']);
                $out = $handler->apply($this->fill($step['args'] ?? [], $context), $context);

                $context->outputs[$step['id'] ?? "step{$index}"] = $out;
                if (isset($out['tenant_id'])) {
                    $context->tenantId = (int) $out['tenant_id'];
                }
            }

            $application = BlueprintApplication::create([
                'blueprint_id' => $blueprint->id, 'blueprint_version' => $blueprint->version, 'params_hash' => $hash, 'params' => $params,
                'outputs' => $context->outputs, 'tenant_id' => $context->tenantId, 'applied_by' => $actorId,
            ]);

            $this->events->publish(new Event('blueprint.applied', ['blueprint_id' => $blueprint->id, 'application_id' => $application->id, 'tenant_id' => $context->tenantId],
                $context->tenantId, 'blueprint', $application->id, $actorId ? 'user' : null, $actorId));

            return $application;
        });
    }

    /** @return array<string, mixed> */
    private function cleanParams(Blueprint $blueprint, array $given): array
    {
        $clean = [];

        foreach ($blueprint->params as $field => $type) {
            $optional = str_ends_with($type, '?');
            $value = $given[$field] ?? null;

            if ($value === null || $value === '') {
                if (! $optional) {
                    throw new BlueprintError("Please provide [{$field}].");
                }

                continue;
            }

            $ok = match (rtrim($type, '?')) {
                'int' => is_numeric($value),
                'bool' => is_bool($value),
                default => is_string($value) || is_numeric($value),
            };
            if (! $ok) {
                throw new BlueprintError("[{$field}] has the wrong type.");
            }

            $clean[$field] = rtrim($type, '?') === 'int' ? (int) $value : (is_string($value) ? trim($value) : $value);
        }

        foreach (array_diff_key($given, $blueprint->params) as $unknown => $_) {
            throw new BlueprintError("[{$unknown}] is not a parameter of this blueprint.");
        }

        return $clean;
    }

    /** Replaces {{param}} and {{stepId.output}} in every string; a value that is exactly one placeholder keeps its type. */
    private function fill(array $args, BlueprintContext $context): array
    {
        return array_map(function ($node) use ($context) {
            if (is_array($node)) {
                return $this->fill($node, $context);
            }
            if (! is_string($node)) {
                return $node;
            }

            $lookup = function (string $path) use ($context) {
                $found = data_get(['params' => $context->params] + $context->outputs, str_starts_with($path, 'params.') ? $path : $path);

                return $found ?? data_get($context->params, $path);
            };

            if (preg_match('/^\{\{\s*([\w.]+)\s*\}\}$/', $node, $m)) {
                return $lookup($m[1]);
            }

            return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', fn ($m) => (string) ($lookup($m[1]) ?? ''), $node);
        }, $args);
    }
}
