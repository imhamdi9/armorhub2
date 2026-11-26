<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * A payment provider driver. Drivers are registered in PaymentProviders; which one is active,
 * and all of its credentials, are settings an admin edits at runtime. Adding a gateway means
 * adding one class like this and registering it: nothing else changes.
 */
interface PaymentProvider
{
    /** Stable identifier used in settings keys and the webhook URL, e.g. "stripe". */
    public function key(): string;

    public function label(): string;

    /** One or two sentences an admin sees: what to do in the provider's dashboard. */
    public function help(): string;

    /**
     * The settings this provider needs, keyed by field name:
     * ['label' => string, 'type' => 'string'|'secret'|'text'|'select'|'bool', 'required' => bool,
     *  'help' => string, 'default' => mixed, 'options' => array]
     *
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array;

    /** @param  array<string, mixed>  $config  field => value */
    public function isConfigured(array $config): bool;

    /**
     * Start charging for an order.
     *
     * @param  array<string, mixed>  $config
     * @return array{status: 'completed'|'redirect'|'failed', transaction_id: ?string, url?: string, message?: string}
     *
     * @throws PaymentUnavailable when the provider cannot be used right now
     */
    public function charge(Order $order, float $amount, ?string $method, array $config): array;

    /**
     * Verify and interpret a webhook. Returns null for events we do not care about.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidWebhook when the signature is wrong, stale or missing
     */
    public function parseWebhook(Request $request, array $config): ?PaymentEvent;

    /**
     * Check the credentials against the provider, if it offers a harmless call for that.
     *
     * @param  array<string, mixed>  $config
     * @return array{ok: bool, message: string}
     */
    public function test(array $config): array;
}
