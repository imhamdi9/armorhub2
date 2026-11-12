<?php

namespace App\Services;

use App\Models\CentreWalletEntry;
use App\Models\InstallationDispute;
use App\Models\InstallationOffer;
use App\Models\InstallationPayment;
use App\Models\InstallationProof;
use App\Models\InstallationRequest;
use App\Models\InstallationReview;
use App\Models\Order;
use App\Models\Partner;
use App\Models\User;
use App\Services\Payment\Money;
use App\Services\Payment\PaymentGateway;
use App\Support\Geo;
use App\Support\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * An installation job from accepted offer to released money.
 *
 * awaiting_payment -> paid -> (in_progress) -> proof_submitted -> released
 *                                   \-> disputed -> released | refunded
 * Money: the customer pays on the platform; the centre's share is HELD until the job is proven and the customer's
 * objection window (default 48 h) has passed without a dispute, or the customer confirms. Then it becomes available to withdraw.
 */
class InstallationJobs
{
    public const TERMS_VERSION = '1';
    /** A photo taken farther than this from the job site is flagged for whoever reviews a dispute. */
    public const SITE_RADIUS_KM = 2.0;

    public function __construct(
        private Settings $settings, private Reputation $reputation, private InstallationNotifier $notify, private PaymentGateway $gateway,
    ) {
    }

    /** What the customer and the centre agree to; shown and recorded when an offer is accepted. */
    public static function terms(): array
    {
        return [
            'version' => self::TERMS_VERSION,
            'lines' => [
                'Payment is made on the platform and held until the work is proven. The centre is paid after the customer confirms, or after the objection window passes without a dispute.',
                'The platform warranty covers only work arranged and paid through the platform.',
                'The platform is not responsible for any sale, service or payment made outside the platform.',
                'Do not share phone numbers or arrange payment outside the platform. Doing so ends the warranty and may suspend the account.',
            ],
        ];
    }

    public function feePercent(InstallationRequest $r, Partner $centre): float
    {
        $market = $this->settings->get("installation.fee_percent.{$r->market}");
        $base = $market !== null && $market !== '' ? (float) $market : (float) $this->settings->get('installation.fee_percent');

        return max(0.0, round($base - $this->reputation->feeDiscount($centre), 2));
    }

    // ------------------------------------------------------------------ accept and pay

    public function accept(InstallationRequest $r, InstallationOffer $offer, User $by, bool $terms, int $points = 0): InstallationRequest
    {
        if (! $terms) {
            throw ValidationException::withMessages(['accept_terms' => ['Please accept the terms to continue.']]);
        }

        return DB::transaction(function () use ($r, $offer, $by, $points) {
            $r = InstallationRequest::lockForUpdate()->findOrFail($r->id);
            if (! in_array($r->status, ['open', 'offers_received'], true) || $offer->request_id !== $r->id || $offer->status !== 'submitted' || $r->customer_id !== $by->id) {
                throw ValidationException::withMessages(['offer' => ['This offer can no longer be accepted.']]);
            }

            $centre = Partner::withoutGlobalScopes()->findOrFail($offer->partner_id);
            $pct = $this->feePercent($r, $centre);
            $price = round((float) $offer->price, 2);
            $fee = round($price * $pct / 100, 2);

            // Points become a discount that only the platform pays for: never more than its own fee.
            $discount = 0.0;
            $used = 0;
            $value = $this->reputation->pointValue();
            if ($points > 0 && $value > 0 && $fee > 0) {
                $points = min($points, $this->reputation->points($by->id));
                $discount = min(round($points * $value, 2), $fee);
                $used = (int) ceil($discount / $value - 1e-9);
                $discount = min($discount, round($used * $value, 2));
            }

            $r->update([
                'status' => 'awaiting_payment', 'selected_offer_id' => $offer->id, 'price' => $price, 'fee_percent' => $pct, 'fee_amount' => $fee,
                'centre_amount' => round($price - $fee, 2), 'discount_amount' => $discount, 'points_used' => $used, 'pay_amount' => round($price - $discount, 2),
                'terms_accepted_at' => now(), 'terms_version' => self::TERMS_VERSION,
            ]);
            $offer->update(['status' => 'selected']);
            InstallationOffer::where('request_id', $r->id)->where('id', '!=', $offer->id)->where('status', 'submitted')->update(['status' => 'rejected']);

            $this->notify->centre($centre->id, 'Your offer was accepted', 'The customer accepted your offer. You will receive the details when they pay.', '/dashboard/centre-jobs');

            return $r->fresh();
        });
    }

    /** @return array{status: string, checkout_url?: string} */
    public function pay(InstallationRequest $r, ?string $method = null): array
    {
        if ($r->status !== 'awaiting_payment') {
            throw ValidationException::withMessages(['request' => ['Accept an offer before paying.']]);
        }

        // Providers know orders by id. A job is paid through the same providers under the negative id of its request.
        $pseudo = new Order(['currency' => $r->currency, 'company_id' => $r->company_id]);
        $pseudo->id = -$r->id;
        $charge = $this->gateway->charge($pseudo, (float) $r->pay_amount, $method);

        if ($charge['status'] === 'failed') {
            throw ValidationException::withMessages(['payment' => [$charge['message'] ?? 'The payment was declined.']]);
        }

        if ($charge['status'] === 'redirect') {
            InstallationPayment::create(['request_id' => $r->id, 'amount' => $r->pay_amount, 'currency' => $r->currency, 'provider' => $charge['provider'] ?? null, 'transaction_id' => $charge['transaction_id'], 'status' => 'pending']);

            return ['status' => 'redirect', 'checkout_url' => $charge['url']];
        }

        $this->markPaid($r, $charge['transaction_id'] ?? null, $charge['provider'] ?? null);

        return ['status' => 'paid'];
    }

    /** From a verified provider webhook. The amount must be exactly what the job costs. */
    public function webhookPaid(int $requestId, string $reference, ?int $amountMinor, ?float $amountMajor, ?string $currency, string $provider): void
    {
        $r = InstallationRequest::find($requestId);
        if (! $r || $r->status !== 'awaiting_payment') {
            return;
        }
        $expected = Money::minorUnits((float) $r->pay_amount, $r->currency);
        $paid = $amountMinor ?? ($amountMajor !== null ? Money::minorUnits($amountMajor, $r->currency) : null);
        if ($paid !== $expected || ($currency !== null && strtoupper($currency) !== strtoupper($r->currency))) {
            report(new \RuntimeException("Installation payment mismatch for request {$r->id} via {$provider}."));

            return;
        }
        $this->markPaid($r, $reference, $provider);
    }

    public function markPaid(InstallationRequest $r, ?string $txn, ?string $provider): ?InstallationRequest
    {
        return DB::transaction(function () use ($r, $txn, $provider) {
            $r = InstallationRequest::lockForUpdate()->findOrFail($r->id);
            if ($r->status !== 'awaiting_payment') {
                return null;
            }

            $pay = InstallationPayment::where('request_id', $r->id)->where('status', 'pending')->when($txn, fn ($q) => $q->where('transaction_id', $txn))->first();
            $attrs = ['status' => 'completed', 'transaction_id' => $txn, 'provider' => $provider ?? $pay?->provider];
            $pay ? $pay->update($attrs) : InstallationPayment::create($attrs + ['request_id' => $r->id, 'amount' => $r->pay_amount, 'currency' => $r->currency]);

            $offer = InstallationOffer::findOrFail($r->selected_offer_id);
            $centre = Partner::withoutGlobalScopes()->findOrFail($offer->partner_id);

            $r->update(['status' => 'paid', 'paid_at' => now(), 'proxy_extension' => $this->extension(), 'proxy_expires_at' => now()->addDays(60)]);
            CentreWalletEntry::create(['company_id' => $centre->company_id, 'partner_id' => $centre->id, 'request_id' => $r->id, 'amount' => $r->centre_amount, 'currency' => $r->currency, 'status' => 'held']);
            if ($r->points_used > 0) {
                $this->reputation->earn($r->customer_id, -$r->points_used, 'redeemed', $r->id);
            }

            $this->notify->centre($centre->id, 'A customer paid for your job', 'The payment is held by the platform. Open the job for the details and start the work.', "/dashboard/centre-jobs/{$r->id}");
            $this->notify->user($r->customer_id, 'Payment received', 'Your payment is held safely until the work is done and you confirm it.', "/dashboard/installations/{$r->id}");

            return $r->fresh();
        });
    }

    private function extension(): string
    {
        do {
            $e = (string) random_int(100000, 999999);
        } while (InstallationRequest::where('proxy_extension', $e)->where('proxy_expires_at', '>', now())->exists());

        return $e;
    }

    // ------------------------------------------------------------------ the work

    public function start(InstallationRequest $r): InstallationRequest
    {
        if ($r->status !== 'paid') {
            throw ValidationException::withMessages(['request' => ['Only a paid job can be started.']]);
        }
        $r->update(['status' => 'in_progress', 'started_at' => now()]);
        $this->notify->user($r->customer_id, 'The work has started', 'The centre started working on your job.', "/dashboard/installations/{$r->id}");

        return $r->fresh();
    }

    /**
     * Photos and the serial/warranty number, from the centre. Stored privately under random names. Complete proof (a before photo,
     * an after photo and a serial or warranty number) starts the customer's objection window.
     *
     * @param  list<UploadedFile>  $before
     * @param  list<UploadedFile>  $after
     * @param  array{serial?: ?string, note?: ?string, latitude?: mixed, longitude?: mixed, taken_at?: ?string}  $meta
     */
    public function submitProof(InstallationRequest $r, User $by, array $before, array $after, array $meta): InstallationRequest
    {
        if (! in_array($r->status, ['paid', 'in_progress', 'proof_submitted'], true)) {
            throw ValidationException::withMessages(['request' => ['Proof can only be added to a paid job that is not finished.']]);
        }

        DB::transaction(function () use ($r, $by, $before, $after, $meta) {
            foreach (['before' => $before, 'after' => $after] as $kind => $files) {
                foreach ($files as $file) {
                    $this->storeProof($r, $by, 'centre', $kind, $file, $meta);
                }
            }
            if (! empty($meta['serial'])) {
                InstallationProof::create(['request_id' => $r->id, 'user_id' => $by->id, 'side' => 'centre', 'kind' => 'serial', 'text' => $meta['serial']]);
            }
        });

        $has = fn (string $kind) => InstallationProof::where('request_id', $r->id)->where('side', 'centre')->where('kind', $kind)->exists();
        if ($r->status !== 'proof_submitted' && $has('before') && $has('after') && ($has('serial') || $has('document'))) {
            $hours = (int) $this->settings->get('installation.objection_hours');
            $deadline = now()->addHours($hours);
            $r->update(['status' => 'proof_submitted', 'proof_submitted_at' => now(), 'objection_deadline' => $deadline, 'proxy_expires_at' => $deadline]);
            $this->notify->user($r->customer_id, 'Please check the finished work', "The centre says the job is done. Confirm it, or open a dispute within {$hours} hours. After that the payment is released.", "/dashboard/installations/{$r->id}");
        }

        return $r->fresh();
    }

    public function storeProof(InstallationRequest $r, User $by, string $side, string $kind, UploadedFile $file, array $meta = []): InstallationProof
    {
        $name = Str::uuid().'.'.($file->extension() === 'png' ? 'png' : 'jpg');
        Storage::disk('local')->putFileAs('installation-proofs', $file, $name);

        $lat = isset($meta['latitude']) && $meta['latitude'] !== '' ? (float) $meta['latitude'] : null;
        $lng = isset($meta['longitude']) && $meta['longitude'] !== '' ? (float) $meta['longitude'] : null;
        $km = $lat !== null && $lng !== null ? round(Geo::km($r->latitude, $r->longitude, $lat, $lng), 2) : null;
        $taken = ! empty($meta['taken_at']) ? \Illuminate\Support\Carbon::parse($meta['taken_at']) : null;
        $flagged = ($km !== null && $km > self::SITE_RADIUS_KM) || ($taken !== null && ($taken->lt(now()->subDays(3)) || $taken->gt(now()->addHour())));

        return InstallationProof::create([
            'request_id' => $r->id, 'user_id' => $by->id, 'side' => $side, 'kind' => $kind, 'path' => "installation-proofs/{$name}", 'text' => $meta['note'] ?? null,
            'taken_at' => $taken, 'latitude' => $lat, 'longitude' => $lng, 'distance_km' => $km, 'flagged' => $flagged,
        ]);
    }

    // ------------------------------------------------------------------ release

    public function confirm(InstallationRequest $r, User $by): InstallationRequest
    {
        if ($r->status !== 'proof_submitted' || $r->customer_id !== $by->id) {
            throw ValidationException::withMessages(['request' => ['There is nothing to confirm yet.']]);
        }

        return $this->release($r);
    }

    public function release(InstallationRequest $r): InstallationRequest
    {
        return DB::transaction(function () use ($r) {
            $r = InstallationRequest::lockForUpdate()->findOrFail($r->id);
            if (! in_array($r->status, ['proof_submitted', 'disputed'], true)) {
                return $r;
            }
            $days = (int) $this->settings->get('installation.warranty_days');
            $r->update(['status' => 'released', 'released_at' => now(), 'warranty_ends_at' => now()->addDays($days)]);
            CentreWalletEntry::where('request_id', $r->id)->where('status', 'held')->update(['status' => 'available']);
            $this->reputation->earn($r->customer_id, $this->reputation->pointsForPayment((float) $r->pay_amount), 'job', $r->id);

            $centre = $this->centreOf($r);
            $this->reputation->refresh($centre);
            $this->notify->centre($centre->id, 'Payment released', "{$r->centre_amount} {$r->currency} is now available in your wallet.", '/dashboard/wallet');
            $this->notify->user($r->customer_id, 'Job completed', 'The job is complete and covered by the platform warranty. Please rate the centre.', "/dashboard/installations/{$r->id}");

            return $r->fresh();
        });
    }

    /** Called by the scheduler: proven jobs whose objection window passed without a dispute are released. */
    public function releaseDue(): int
    {
        $n = 0;
        InstallationRequest::where('status', 'proof_submitted')->where('objection_deadline', '<=', now())->get()->each(function ($r) use (&$n) {
            $this->release($r);
            $n++;
        });

        return $n;
    }

    public function expireOld(): int
    {
        return InstallationRequest::whereIn('status', ['open', 'offers_received'])->where('expires_at', '<', now())->update(['status' => 'expired']);
    }

    public function centreOf(InstallationRequest $r): Partner
    {
        return Partner::withoutGlobalScopes()->findOrFail(InstallationOffer::findOrFail($r->selected_offer_id)->partner_id);
    }

    // ------------------------------------------------------------------ reviews

    public function review(InstallationRequest $r, User $by, int $rating, ?string $comment): InstallationReview
    {
        if ($r->status !== 'released' || $r->customer_id !== $by->id) {
            throw ValidationException::withMessages(['review' => ['You can rate a job after it is completed.']]);
        }
        if (InstallationReview::where('request_id', $r->id)->exists()) {
            throw ValidationException::withMessages(['review' => ['You already rated this job.']]);
        }
        $centre = $this->centreOf($r);
        $review = InstallationReview::create(['request_id' => $r->id, 'partner_id' => $centre->id, 'customer_id' => $by->id, 'rating' => $rating, 'comment' => $comment]);
        $this->reputation->earn($by->id, 5, 'review', $r->id);
        $this->reputation->refresh($centre);

        return $review;
    }

    // ------------------------------------------------------------------ disputes

    public function openDispute(InstallationRequest $r, User $by, string $side, string $type, string $description): InstallationDispute
    {
        if (! in_array($r->status, ['paid', 'in_progress', 'proof_submitted'], true)) {
            throw ValidationException::withMessages(['dispute' => ['A dispute can only be opened while the payment is still held.']]);
        }
        if ($side === 'customer' && $r->status === 'proof_submitted' && $r->objection_deadline && $r->objection_deadline->isPast()) {
            throw ValidationException::withMessages(['dispute' => ['The objection window has passed.']]);
        }

        return DB::transaction(function () use ($r, $by, $side, $type, $description) {
            $r->update(['status' => 'disputed']);
            $d = InstallationDispute::create(['request_id' => $r->id, 'opened_by' => $by->id, 'side' => $side, 'type' => $type, 'description' => $description]);

            $side === 'customer'
                ? $this->notify->centre($this->centreOf($r)->id, 'The customer opened a dispute', 'The payment is on hold while the platform reviews the job. Please add your evidence.', "/dashboard/centre-jobs/{$r->id}")
                : $this->notify->user($r->customer_id, 'The centre opened a dispute', 'The payment stays on hold while the platform reviews the job. Please add your evidence.', "/dashboard/installations/{$r->id}");
            $this->notify->admins('A dispute needs a decision', "Job #{$r->id}: {$type}.", '/dashboard/admin/disputes');

            return $d;
        });
    }

    /** @param 'release'|'refund_full'|'refund_partial' $decision */
    public function resolveDispute(InstallationDispute $d, User $admin, string $decision, ?float $refund, ?string $note): InstallationDispute
    {
        if ($d->status !== 'open') {
            throw ValidationException::withMessages(['dispute' => ['This dispute is already decided.']]);
        }

        return DB::transaction(function () use ($d, $admin, $decision, $refund, $note) {
            $r = InstallationRequest::lockForUpdate()->findOrFail($d->request_id);
            $centre = $this->centreOf($r);
            $pay = (float) $r->pay_amount;

            if ($decision === 'refund_partial' && ($refund === null || $refund <= 0 || $refund >= $pay)) {
                throw ValidationException::withMessages(['refund_amount' => ['A partial refund must be more than 0 and less than what the customer paid.']]);
            }

            if ($decision === 'release') {
                $this->release($r);
            } elseif ($decision === 'refund_full') {
                CentreWalletEntry::where('request_id', $r->id)->whereIn('status', ['held', 'available'])->update(['status' => 'void']);
                $r->update(['status' => 'refunded', 'refund_amount' => $pay, 'refund_status' => 'pending']);
                if ($r->points_used > 0) {
                    $this->reputation->earn($r->customer_id, $r->points_used, 'refund_reversal', $r->id);
                }
                $centre->forceFill(['disputes_lost' => $centre->disputes_lost + 1])->save();
            } else {
                $remaining = round((float) $r->price - $refund, 2);
                $fee = round($remaining * (float) $r->fee_percent / 100, 2);
                $centreAmount = round($remaining - $fee, 2);
                $r->update(['status' => 'released', 'released_at' => now(), 'warranty_ends_at' => now()->addDays((int) $this->settings->get('installation.warranty_days')),
                    'refund_amount' => $refund, 'refund_status' => 'pending', 'fee_amount' => $fee, 'centre_amount' => $centreAmount]);
                CentreWalletEntry::where('request_id', $r->id)->whereIn('status', ['held', 'available'])->update(['status' => 'available', 'amount' => $centreAmount]);
                $centre->forceFill(['disputes_lost' => $centre->disputes_lost + 1])->save();
            }

            $d->update(['status' => 'resolved', 'decision' => $decision, 'refund_amount' => $decision === 'refund_full' ? $pay : $refund, 'decision_note' => $note, 'decided_by' => $admin->id, 'decided_at' => now()]);
            $this->reputation->refresh($centre);

            $words = match ($decision) {
                'release' => 'The platform checked the job and released the payment to the centre.',
                'refund_full' => 'The platform decided in your favour: you will be refunded in full.',
                default => "The platform decided on a partial refund of {$refund} {$r->currency}.",
            };
            $this->notify->user($r->customer_id, 'Dispute decided', $words, "/dashboard/installations/{$r->id}");
            $this->notify->centre($centre->id, 'Dispute decided', $decision === 'release' ? 'The payment was released to your wallet.' : 'The platform decided on a refund to the customer.', "/dashboard/centre-jobs/{$r->id}");

            return $d->fresh();
        });
    }
}
