<?php

namespace App\Services\Payment\Providers;

use App\Models\Order;
use App\Services\Payment\InvalidWebhook;
use App\Services\Payment\Money;
use App\Services\Payment\PaymentEvent;
use App\Services\Payment\PaymentProvider;
use App\Services\Payment\PaymentUnavailable;
use App\Support\Security\SafeUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * A hosted-checkout payment gateway described entirely by settings, so most gateways that work like
 * "POST an order, get a payment-page URL, receive a signed webhook when paid" (Moyasar, Tap, PayTabs,
 * Mollie, ...) can be connected from the admin panel without writing code. Gateways that need OAuth,
 * multi-step flows or a different signing scheme still need their own driver.
 */
class HttpProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'http';
    }

    public function label(): string
    {
        return 'Custom gateway (HTTP API)';
    }

    public function help(): string
    {
        return 'Connect any gateway that creates a hosted payment page from an HTTP request and confirms payment with an HMAC-signed webhook. Placeholders for the request body: {order_id} {amount} {amount_minor} {currency} {currency_lower} {description} {success_url} {cancel_url}.';
    }

    public function fields(): array
    {
        return [
            'create_url' => ['label' => 'Create-payment URL (https)', 'type' => 'string', 'required' => true,
                'help' => 'The gateway endpoint that starts a payment.'],
            'auth_header' => ['label' => 'Auth header name', 'type' => 'string', 'default' => 'Authorization', 'help' => 'e.g. Authorization or X-Api-Key'],
            'auth_value' => ['label' => 'Auth header value', 'type' => 'secret', 'required' => true, 'help' => 'e.g. Bearer sk_live_... (kept encrypted)'],
            'content_type' => ['label' => 'Request format', 'type' => 'select', 'default' => 'json',
                'options' => ['json' => 'JSON', 'form' => 'Form (x-www-form-urlencoded)']],
            'request_body' => ['label' => 'Request body template', 'type' => 'text', 'required' => true,
                'default' => '{"amount": "{amount_minor}", "currency": "{currency}", "description": "{description}", "metadata": {"order_id": "{order_id}"}, "success_url": "{success_url}", "cancel_url": "{cancel_url}"}',
                'help' => 'A JSON object. A value that is exactly a placeholder keeps its type (numbers stay numbers).'],
            'response_url_path' => ['label' => 'Response: payment page URL path', 'type' => 'string', 'required' => true, 'help' => 'Dotted path in the JSON reply, e.g. data.checkout_url'],
            'response_reference_path' => ['label' => 'Response: payment id path', 'type' => 'string', 'required' => true, 'help' => 'e.g. data.id'],

            'webhook_secret' => ['label' => 'Webhook signing secret', 'type' => 'secret', 'required' => true],
            'webhook_signature_header' => ['label' => 'Webhook signature header', 'type' => 'string', 'default' => 'X-Signature', 'required' => true],
            'webhook_signature_encoding' => ['label' => 'Signature encoding', 'type' => 'select', 'default' => 'hex',
                'options' => ['hex' => 'Hex', 'base64' => 'Base64'], 'help' => 'HMAC-SHA256 of the raw body. A "sha256=" prefix is accepted.'],
            'webhook_status_path' => ['label' => 'Webhook: status path', 'type' => 'string', 'required' => true, 'help' => 'e.g. data.status'],
            'webhook_paid_values' => ['label' => 'Webhook: statuses that mean paid', 'type' => 'string', 'default' => 'paid,succeeded,captured,completed', 'required' => true],
            'webhook_order_path' => ['label' => 'Webhook: order id path', 'type' => 'string', 'required' => true, 'help' => 'Where your order_id comes back, e.g. data.metadata.order_id'],
            'webhook_reference_path' => ['label' => 'Webhook: payment id path', 'type' => 'string', 'required' => true],
            'webhook_amount_path' => ['label' => 'Webhook: amount path', 'type' => 'string', 'required' => true, 'help' => 'Required so underpayments are never accepted.'],
            'webhook_amount_unit' => ['label' => 'Webhook amount is in', 'type' => 'select', 'default' => 'minor',
                'options' => ['minor' => 'Smallest unit (cents/halalas)', 'major' => 'Whole units (12.50)']],
            'webhook_currency_path' => ['label' => 'Webhook: currency path (optional)', 'type' => 'string'],
        ];
    }

    public function isConfigured(array $config): bool
    {
        foreach (array_keys(array_filter($this->fields(), fn ($f) => $f['required'] ?? false)) as $field) {
            if (! filled($config[$field] ?? null)) {
                return false;
            }
        }

        return $this->template($config) !== null && SafeUrl::problem((string) $config['create_url']) === null;
    }

    public function charge(Order $order, float $amount, ?string $method, array $config): array
    {
        if (! $this->isConfigured($config)) {
            throw new PaymentUnavailable('Online payments are not configured yet.');
        }

        $web = rtrim(config('platform.web_url'), '/');
        $values = [
            'order_id' => (string) $order->id,
            'amount' => number_format($amount, 2, '.', ''),
            'amount_minor' => Money::minorUnits($amount, $order->currency),
            'currency' => strtoupper($order->currency),
            'currency_lower' => strtolower($order->currency),
            'description' => "Order #{$order->id}",
            'success_url' => "{$web}/dashboard/orders/{$order->id}?checkout=success",
            'cancel_url' => "{$web}/dashboard/orders/{$order->id}?checkout=cancelled",
        ];

        $body = $this->fill($this->template($config), $values);

        try {
            $request = Http::withHeaders([(string) ($config['auth_header'] ?: 'Authorization') => (string) $config['auth_value']])
                ->timeout(15)->withoutRedirecting()->acceptJson();

            $response = ($config['content_type'] ?? 'json') === 'form'
                ? $request->asForm()->post($config['create_url'], $this->flatten($body))
                : $request->asJson()->post($config['create_url'], $body);
        } catch (ConnectionException) {
            throw new PaymentUnavailable('The payment provider could not be reached. Please try again.');
        }

        $url = data_get($response->json(), $config['response_url_path']);
        $reference = data_get($response->json(), $config['response_reference_path']);

        // Only hand the customer a payment-page URL that is an https link.
        if ($response->failed() || ! is_string($url) || ! preg_match('#^https://#i', $url) || $reference === null) {
            report(new \RuntimeException('Custom gateway checkout failed: HTTP '.$response->status()));

            return ['status' => 'failed', 'transaction_id' => null, 'message' => 'The payment could not be started.'];
        }

        return ['status' => 'redirect', 'transaction_id' => (string) $reference, 'url' => $url];
    }

    public function parseWebhook(Request $request, array $config): ?PaymentEvent
    {
        $secret = $config['webhook_secret'] ?? null;
        $header = (string) $request->header((string) ($config['webhook_signature_header'] ?? 'X-Signature'));

        if (! $secret || $header === '') {
            throw new InvalidWebhook('Missing signature or secret.');
        }

        $mac = hash_hmac('sha256', $request->getContent(), $secret, true);
        $expected = ($config['webhook_signature_encoding'] ?? 'hex') === 'base64' ? base64_encode($mac) : bin2hex($mac);
        $given = preg_replace('/^sha256=/i', '', trim($header));

        if (! hash_equals($expected, $given)) {
            throw new InvalidWebhook('Signature mismatch.');
        }

        $json = json_decode($request->getContent(), true);

        if (! is_array($json)) {
            throw new InvalidWebhook('Not JSON.');
        }

        $status = strtolower((string) data_get($json, $config['webhook_status_path']));
        $paidValues = array_map('strtolower', array_map('trim', explode(',', (string) $config['webhook_paid_values'])));

        if (! in_array($status, $paidValues, true)) {
            return null; // pending, failed, refunded...: nothing to do
        }

        $orderId = data_get($json, $config['webhook_order_path']);
        $reference = data_get($json, $config['webhook_reference_path']);
        $amount = data_get($json, $config['webhook_amount_path']);

        if (! is_numeric($orderId) || $reference === null || ! is_numeric($amount)) {
            throw new InvalidWebhook('The webhook is missing the order, payment id or amount.');
        }

        $currency = $config['webhook_currency_path'] ? data_get($json, $config['webhook_currency_path']) : null;
        $major = ($config['webhook_amount_unit'] ?? 'minor') === 'major';

        // Whole-unit amounts are converted by the handler using the order's own currency.
        return new PaymentEvent('paid', (string) $reference, (int) $orderId, $major ? null : (int) $amount, $currency, $major ? (float) $amount : null);
    }

    public function test(array $config): array
    {
        if (! $this->isConfigured($config)) {
            return ['ok' => false, 'message' => 'Fill in every required field first (and use a public https address).'];
        }

        return ['ok' => true, 'message' => 'The configuration is complete. Place a small real order to confirm the gateway end to end.'];
    }

    /** The admin's JSON template as an array, or null if it is not a valid JSON object. */
    private function template(array $config): ?array
    {
        $decoded = json_decode((string) ($config['request_body'] ?? ''), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** Substitute placeholders into every string value, keeping the type when a value is exactly one placeholder. */
    private function fill(array $template, array $values): array
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

    /** @return array<string, scalar> dotted/bracketed keys for form posts */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";
            is_array($value) ? $out += $this->flatten($value, $name) : $out[$name] = $value;
        }

        return $out;
    }
}
