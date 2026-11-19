<?php

namespace App\Services;

use App\Models\InstallationBooking;
use App\Models\InstallationRequest;
use App\Models\User;
use App\Notifications\SimpleNotice;

/**
 * A booked time passed and the centre has uploaded no proof of work. The customer is asked whether anyone came (they may open a dispute while the
 * payment is held), the centre is reminded, and ArmorHub is told. Once per booking.
 */
class BookingNoShows
{
    public const GRACE_HOURS = 12;

    public function __construct(private InstallationNotifier $notify)
    {
    }

    public function run(): int
    {
        $flagged = 0;
        InstallationBooking::where('status', 'booked')->whereNull('no_show_flagged_at')->where('ends_at', '<=', now()->subHours(self::GRACE_HOURS))->get()->each(function (InstallationBooking $b) use (&$flagged) {
            $request = InstallationRequest::find($b->request_id);
            if (! $request || ! in_array($request->status, ['paid', 'in_progress'], true)) {
                return;
            }
            $b->update(['no_show_flagged_at' => now()]);
            User::find($request->customer_id)?->notify(new SimpleNotice('Did the centre come?', ':subject: the booked time has passed. If nobody came, open a dispute from the job while the payment is held.', "/dashboard/installations/{$request->id}", ['subject' => (string) $request->subject]));
            $this->notify->centre($b->partner_id, 'Your booked time passed', ':subject: upload the proof of work, or the customer may open a dispute.', "/dashboard/centre-jobs/{$request->id}", ['subject' => (string) $request->subject]);
            $this->notify->admins('Possible no-show', 'Job #:id: the booked time passed with no proof.', '/dashboard/admin/disputes', ['id' => (string) $request->id]);
            $flagged++;
        });

        return $flagged;
    }
}
