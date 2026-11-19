<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SandboxCharge;
use App\Services\Payment\Money;
use App\Services\Payment\PaymentEvent;
use App\Services\Payment\PaymentProviders;
use App\Services\Payment\PaymentWebhookHandler;
use Illuminate\Http\Request;

/** The sandbox provider's hosted checkout: show what is being paid, take a test card or wallet code, and confirm the payment like a real provider would. */
class SandboxCheckoutController extends Controller
{
    private const MAX_ATTEMPTS = 6;

    /** Test cards: the number decides the result. */
    private const CARDS = [
        '4242424242424242' => null,                                  // approved
        '4000000000000002' => 'Your card was declined.',
        '4000000000009995' => 'Your card has insufficient funds.',
        '4000000000000069' => 'Your card has expired.',
    ];

    public function show(string $ref, PaymentProviders $providers)
    {
        $charge = $this->charge($ref, $providers);

        return ['ref' => $charge->ref, 'amount' => (float) $charge->amount, 'currency' => $charge->currency, 'status' => $charge->status, 'return_url' => $charge->return_url, 'error' => $charge->last_error];
    }

    public function pay(Request $request, string $ref, PaymentProviders $providers, PaymentWebhookHandler $handler)
    {
        $charge = $this->charge($ref, $providers);
        if ($charge->status !== 'pending') {
            return response()->json(['status' => $charge->status, 'return_url' => $charge->return_url]);
        }
        $data = $request->validate([
            'method' => 'required|in:card,wallet',
            'name' => 'required_if:method,card|nullable|string|max:80', 'number' => 'required_if:method,card|nullable|string|max:30',
            'expiry' => ['required_if:method,card', 'nullable', 'regex:/^(0[1-9]|1[0-2])\s*\/\s*\d{2}$/'], 'cvc' => 'required_if:method,card|nullable|digits_between:3,4',
            'phone' => 'required_if:method,wallet|nullable|string|max:20', 'code' => 'required_if:method,wallet|nullable|string|max:10',
        ]);

        $error = $data['method'] === 'card' ? $this->checkCard($data) : $this->checkWallet($data);
        $charge->increment('attempts');

        if ($error !== null) {
            $charge->update(['last_error' => $error]);
            if ($charge->attempts >= self::MAX_ATTEMPTS) {
                $charge->update(['status' => 'cancelled']);
            }

            return response()->json(['status' => $charge->status === 'cancelled' ? 'cancelled' : 'declined', 'message' => $error, 'return_url' => $charge->return_url], 422);
        }

        // The same step a real provider's webhook takes: what was paid is checked against what the purchase costs, then it is completed.
        $charge->update(['status' => 'paid', 'last_error' => null]);
        $handler->handle('sandbox', new PaymentEvent('paid', $charge->ref, (int) $charge->payment_id, Money::minorUnits((float) $charge->amount, $charge->currency), $charge->currency));

        return ['status' => 'paid', 'return_url' => $charge->return_url];
    }

    public function cancel(string $ref, PaymentProviders $providers)
    {
        $charge = $this->charge($ref, $providers);
        if ($charge->status === 'pending') {
            $charge->update(['status' => 'cancelled']);
        }

        return ['status' => $charge->status, 'return_url' => $charge->return_url];
    }

    private function charge(string $ref, PaymentProviders $providers): SandboxCharge
    {
        abort_unless($providers->isConfigured('sandbox'), 404);

        return SandboxCharge::where('ref', $ref)->firstOrFail();
    }

    /** @param array<string, mixed> $d */
    private function checkCard(array $d): ?string
    {
        $number = preg_replace('/\D+/', '', (string) $d['number']) ?? '';
        if (! array_key_exists($number, self::CARDS)) {
            return 'This is test mode: use the test card 4242 4242 4242 4242.';
        }
        [$mm, $yy] = array_map('intval', array_map('trim', explode('/', (string) $d['expiry'])));
        if ((2000 + $yy) < (int) now()->year || ((2000 + $yy) === (int) now()->year && $mm < (int) now()->month)) {
            return 'Your card has expired.';
        }

        return self::CARDS[$number];
    }

    /** @param array<string, mixed> $d */
    private function checkWallet(array $d): ?string
    {
        if (preg_replace('/\D+/', '', (string) $d['phone']) === '' || strlen(preg_replace('/\D+/', '', (string) $d['phone'])) < 8) {
            return 'Enter the phone number of your wallet.';
        }

        return trim((string) $d['code']) === '123456' ? null : 'The code is not right. In test mode it is 123456.';
    }
}
