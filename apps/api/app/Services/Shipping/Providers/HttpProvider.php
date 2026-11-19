<?php

namespace App\Services\Shipping\Providers;

use App\Models\Order;
use App\Models\Shipment;
use App\Services\Payment\InvalidWebhook;
use App\Services\Shipping\ShippingProvider;
use App\Services\Shipping\ShippingUnavailable;
use App\Services\Shipping\Status;
use App\Services\Shipping\TrackingEvent;
use App\Support\FillsTemplates;
use App\Support\Security\SafeUrl;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Connects any carrier that has a JSON/form HTTP API (SMSA, Aramex, Bosta, J&T, DHL, ...) from the admin
 * panel: how to book a parcel, where the tracking number is in the reply, how to read tracking updates and
 * webhooks. Carriers with OAuth, SOAP/XML or multi-step booking need their own driver.
 */
class HttpProvider implements ShippingProvider
{
    use FillsTemplates;

    public function key(): string
    {
        return 'http';
    }

    public function label(): string
    {
        return 'Custom carrier (HTTP API)';
    }

    public function help(): string
    {
        return 'Describe your carrier\'s API: the booking URL and body, and where the tracking number is in the reply. Optional: price quotes, tracking lookups and signed webhooks. Placeholders for request bodies: {order_id} {declared_value} {currency} {recipient_name} {phone} {address_line1} {address_line2} {city} {region} {postal_code} {country} {service} {weight_kg} {origin_name} {origin_phone} {origin_line1} {origin_city} {origin_country}.';
    }

    public function fields(): array
    {
        $path = fn (string $label, string $help = '', bool $required = false) => ['label' => $label, 'type' => 'string', 'required' => $required, 'help' => $help];

        return [
            'auth_header' => ['label' => 'Auth header name', 'type' => 'string', 'default' => 'Authorization', 'help' => 'e.g. Authorization or X-Api-Key'],
            'auth_value' => ['label' => 'Auth header value', 'type' => 'secret', 'help' => 'e.g. Bearer your-key (kept encrypted). Leave empty if the carrier needs none.'],
            'content_type' => ['label' => 'Request format', 'type' => 'select', 'default' => 'json', 'options' => ['json' => 'JSON', 'form' => 'Form (x-www-form-urlencoded)']],
            'default_weight_kg' => ['label' => 'Default parcel weight (kg)', 'type' => 'float', 'default' => 1, 'rules' => ['min:0.01', 'max:1000']],
            'origin_name' => $path('Sender name'),
            'origin_phone' => $path('Sender phone'),
            'origin_line1' => $path('Sender address'),
            'origin_city' => $path('Sender city'),
            'origin_country' => $path('Sender country (2 letters)'),

            'rates_url' => $path('Price-quote URL (https, optional)', 'Leave empty if the carrier has no quote API.'),
            'rates_body' => ['label' => 'Price-quote body template', 'type' => 'text', 'default' => '{"origin_city": "{origin_city}", "destination_city": "{city}", "destination_country": "{country}", "weight_kg": "{weight_kg}"}'],
            'rates_list_path' => $path('Quote reply: list path', 'e.g. data.rates'),
            'rate_service_path' => $path('Quote reply: service code (inside each item)', 'e.g. code'),
            'rate_label_path' => $path('Quote reply: service name (inside each item)'),
            'rate_price_path' => $path('Quote reply: price (inside each item)'),
            'rate_eta_path' => $path('Quote reply: delivery days (inside each item, optional)'),

            'create_url' => $path('Book-shipment URL (https)', 'The carrier endpoint that creates a shipment.', true),
            'create_body' => ['label' => 'Book-shipment body template', 'type' => 'text', 'required' => true,
                'default' => '{"reference": "{order_id}", "service": "{service}", "weight": "{weight_kg}", "declared_value": "{declared_value}", "currency": "{currency}", "sender": {"name": "{origin_name}", "phone": "{origin_phone}", "address": "{origin_line1}", "city": "{origin_city}", "country": "{origin_country}"}, "recipient": {"name": "{recipient_name}", "phone": "{phone}", "address": "{address_line1}", "address2": "{address_line2}", "city": "{city}", "postal_code": "{postal_code}", "country": "{country}"}}',
                'help' => 'A JSON object. A value that is exactly a placeholder keeps its type.'],
            'response_reference_path' => $path('Reply: shipment id path', 'e.g. data.id'),
            'response_tracking_path' => $path('Reply: tracking number path', 'e.g. data.tracking_number', true),
            'response_tracking_url_path' => $path('Reply: tracking link path (optional)'),
            'response_label_path' => $path('Reply: label PDF link path (optional)'),
            'response_cost_path' => $path('Reply: shipping cost path (optional)'),
            'carrier_name' => $path('Carrier name shown to customers', 'e.g. SMSA Express'),
            'tracking_url_template' => ['label' => 'Tracking link template (https, optional)', 'type' => 'string', 'help' => 'Used when the reply has no tracking link. {tracking} is replaced.', 'rules' => ['nullable', 'regex:~^https://[^\s<>"\']+$~i', 'max:500']],

            'track_url' => $path('Tracking URL (https, optional)', 'A GET request; {tracking} and {reference} are replaced. Leave empty if you rely on webhooks.'),
            'track_events_path' => $path('Tracking reply: list of events path', 'e.g. data.events'),
            'track_status_path' => $path('Tracking reply: status (inside each event)'),
            'track_description_path' => $path('Tracking reply: description (inside each event, optional)'),
            'track_location_path' => $path('Tracking reply: location (inside each event, optional)'),
            'track_time_path' => $path('Tracking reply: time (inside each event, optional)'),
            'status_map' => ['label' => 'Status words', 'type' => 'text', 'help' => 'Map the carrier\'s status words to ours, e.g. DLV=delivered, OFD=out_for_delivery. Common words (delivered, in transit, returned...) already work.'],

            'webhook_secret' => ['label' => 'Webhook signing secret', 'type' => 'secret'],
            'webhook_signature_header' => ['label' => 'Webhook signature header', 'type' => 'string', 'default' => 'X-Signature'],
            'webhook_signature_encoding' => ['label' => 'Signature encoding', 'type' => 'select', 'default' => 'hex', 'options' => ['hex' => 'Hex', 'base64' => 'Base64'], 'help' => 'HMAC-SHA256 of the raw body.'],
            'webhook_status_path' => $path('Webhook: status path', 'e.g. data.status'),
            'webhook_tracking_path' => $path('Webhook: tracking number path', 'e.g. data.tracking_number'),
            'webhook_description_path' => $path('Webhook: description path (optional)'),
            'webhook_location_path' => $path('Webhook: location path (optional)'),
            'webhook_time_path' => $path('Webhook: time path (optional)'),
        ];
    }

    public function isConfigured(array $config): bool
    {
        foreach (['create_url', 'create_body', 'response_tracking_path'] as $field) {
            if (! filled($config[$field] ?? null)) {
                return false;
            }
        }

        return $this->template($config, 'create_body') !== null && SafeUrl::problem((string) $config['create_url']) === null;
    }

    public function quote(Order $order, array $address, array $config): array
    {
        $url = (string) ($config['rates_url'] ?? '');
        if ($url === '' || ! filled($config['rates_list_path'] ?? null) || ! filled($config['rate_price_path'] ?? null) || ($template = $this->template($config, 'rates_body')) === null) {
            return [];
        }

        $json = $this->send($config, 'post', $url, $this->fill($template, $this->values($order, $address, [], $config)));
        $out = [];

        foreach ((array) data_get($json, $config['rates_list_path'], []) as $row) {
            $price = data_get($row, $config['rate_price_path']);
            if (! is_numeric($price)) {
                continue;
            }
            $service = (string) data_get($row, $config['rate_service_path'] ?? '', 'standard');
            $eta = data_get($row, $config['rate_eta_path'] ?? '');
            $out[] = ['service' => $service, 'label' => (string) (data_get($row, $config['rate_label_path'] ?? '') ?: $service),
                'price' => round((float) $price, 2), 'currency' => $order->currency, 'eta_days' => is_numeric($eta) ? (int) $eta : null];
        }

        return $out;
    }

    public function create(Order $order, array $address, array $input, array $config): array
    {
        if (! $this->isConfigured($config)) {
            throw new ShippingUnavailable('This carrier is not configured yet.');
        }

        $json = $this->send($config, 'post', (string) $config['create_url'], $this->fill($this->template($config, 'create_body'), $this->values($order, $address, $input, $config)));

        $tracking = data_get($json, $config['response_tracking_path']);
        if (! is_scalar($tracking) || (string) $tracking === '') {
            report(new \RuntimeException('Carrier booking reply had no tracking number.'));
            throw new ShippingUnavailable('The carrier did not return a tracking number.');
        }

        $scheme = config('platform.allow_insecure_provider_urls') ? '#^https?://#i' : '#^https://#i';
        $link = $this->path($json, $config['response_tracking_url_path'] ?? null);
        $label = $this->path($json, $config['response_label_path'] ?? null);
        $template = (string) ($config['tracking_url_template'] ?? '');
        $cost = $this->path($json, $config['response_cost_path'] ?? null);

        return [
            'reference' => ($ref = $this->path($json, $config['response_reference_path'] ?? null)) !== null ? (string) $ref : null,
            'tracking_number' => (string) $tracking,
            // Links go to customers' browsers: only https ones are kept.
            'tracking_url' => is_string($link) && preg_match($scheme, $link) ? $link : ($template !== '' ? str_replace('{tracking}', rawurlencode((string) $tracking), $template) : null),
            'label_url' => is_string($label) && preg_match($scheme, $label) ? $label : null,
            'cost' => is_numeric($cost) ? round((float) $cost, 2) : null,
            'carrier' => $config['carrier_name'] ?: null,
        ];
    }

    public function track(Shipment $shipment, array $config): array
    {
        $url = (string) ($config['track_url'] ?? '');
        if ($url === '' || ! filled($config['track_events_path'] ?? null) || ! filled($config['track_status_path'] ?? null)) {
            return [];
        }

        $url = str_replace(['{tracking}', '{reference}'], [rawurlencode((string) $shipment->tracking_number), rawurlencode((string) $shipment->reference)], $url);
        $json = $this->send($config, 'get', $url, []);
        $events = [];

        foreach ((array) data_get($json, $config['track_events_path'], []) as $row) {
            $status = Status::normalize((string) data_get($row, $config['track_status_path']), (string) ($config['status_map'] ?? ''));
            if ($status === null) {
                continue;
            }
            $events[] = new TrackingEvent($status, $shipment->tracking_number, $shipment->reference,
                $this->str(data_get($row, $config['track_description_path'] ?? '')), $this->str(data_get($row, $config['track_location_path'] ?? '')),
                $this->time(data_get($row, $config['track_time_path'] ?? '')));
        }

        return $events;
    }

    public function parseWebhook(Request $request, array $config): ?TrackingEvent
    {
        $secret = $config['webhook_secret'] ?? null;
        $header = (string) $request->header((string) ($config['webhook_signature_header'] ?? 'X-Signature'));

        if (! $secret || $header === '') {
            throw new InvalidWebhook('Missing signature or secret.');
        }

        $mac = hash_hmac('sha256', $request->getContent(), $secret, true);
        $expected = ($config['webhook_signature_encoding'] ?? 'hex') === 'base64' ? base64_encode($mac) : bin2hex($mac);

        if (! hash_equals($expected, preg_replace('/^sha256=/i', '', trim($header)))) {
            throw new InvalidWebhook('Signature mismatch.');
        }

        $json = json_decode($request->getContent(), true);
        if (! is_array($json)) {
            throw new InvalidWebhook('Not JSON.');
        }

        $status = Status::normalize((string) data_get($json, $config['webhook_status_path'] ?? ''), (string) ($config['status_map'] ?? ''));
        $tracking = data_get($json, $config['webhook_tracking_path'] ?? '');

        if ($status === null) {
            return null;
        }
        if (! is_scalar($tracking) || (string) $tracking === '') {
            throw new InvalidWebhook('The webhook has no tracking number.');
        }

        return new TrackingEvent($status, (string) $tracking, null, $this->str(data_get($json, $config['webhook_description_path'] ?? '')),
            $this->str(data_get($json, $config['webhook_location_path'] ?? '')), $this->time(data_get($json, $config['webhook_time_path'] ?? '')));
    }

    public function test(array $config): array
    {
        if (! $this->isConfigured($config)) {
            return ['ok' => false, 'message' => 'Fill in the booking URL, the booking body and the tracking-number path first (and use a public https address).'];
        }

        return ['ok' => true, 'message' => 'The configuration is complete. Book a real test parcel to confirm the carrier end to end.'];
    }

    // ------------------------------------------------------------------ helpers

    /** @return array<string, scalar> */
    private function values(Order $order, array $a, array $input, array $config): array
    {
        return [
            'order_id' => (string) $order->id, 'declared_value' => number_format((float) $order->total_amount, 2, '.', ''), 'currency' => strtoupper($order->currency),
            'recipient_name' => (string) ($a['name'] ?? ''), 'phone' => (string) ($a['phone'] ?? ''), 'address_line1' => (string) ($a['line1'] ?? ''),
            'address_line2' => (string) ($a['line2'] ?? ''), 'city' => (string) ($a['city'] ?? ''), 'region' => (string) ($a['region'] ?? ''),
            'postal_code' => (string) ($a['postal_code'] ?? ''), 'country' => strtoupper((string) ($a['country'] ?? '')),
            'service' => (string) ($input['service'] ?? 'standard'), 'weight_kg' => (float) ($config['default_weight_kg'] ?: 1),
            'origin_name' => (string) ($config['origin_name'] ?? ''), 'origin_phone' => (string) ($config['origin_phone'] ?? ''), 'origin_line1' => (string) ($config['origin_line1'] ?? ''),
            'origin_city' => (string) ($config['origin_city'] ?? ''), 'origin_country' => strtoupper((string) ($config['origin_country'] ?? '')),
        ];
    }

    /** @return array<string, mixed> */
    private function send(array $config, string $method, string $url, array $body): array
    {
        if (($problem = SafeUrl::problem($url)) !== null) {
            throw new ShippingUnavailable('The carrier address is not allowed: '.$problem);
        }

        try {
            $header = (string) ($config['auth_header'] ?: 'Authorization');
            $request = Http::timeout(15)->withoutRedirecting()->acceptJson()->withOptions(['curl' => SafeUrl::curlOptions($url)]);
            if (filled($config['auth_value'] ?? null)) {
                $request = $request->withHeaders([$header => (string) $config['auth_value']]);
            }

            $response = match (true) {
                $method === 'get' => $request->get($url),
                ($config['content_type'] ?? 'json') === 'form' => $request->asForm()->post($url, $this->flatten($body)),
                default => $request->asJson()->post($url, $body),
            };
        } catch (ConnectionException) {
            throw new ShippingUnavailable('The carrier could not be reached. Please try again.');
        }

        if ($response->failed()) {
            report(new \RuntimeException('Carrier API error: HTTP '.$response->status()));
            throw new ShippingUnavailable('The carrier refused the request (HTTP '.$response->status().').');
        }

        return (array) $response->json();
    }

    private function template(array $config, string $field): ?array
    {
        $decoded = json_decode((string) ($config[$field] ?? ''), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function path(array $json, ?string $path): mixed
    {
        return filled($path) ? data_get($json, $path) : null;
    }

    private function str(mixed $v): ?string
    {
        return is_scalar($v) && (string) $v !== '' ? mb_substr((string) $v, 0, 250) : null;
    }

    private function time(mixed $v): ?Carbon
    {
        try {
            return is_numeric($v) ? Carbon::createFromTimestamp((int) $v) : (is_string($v) && $v !== '' ? Carbon::parse($v) : null);
        } catch (\Throwable) {
            return null;
        }
    }
}
