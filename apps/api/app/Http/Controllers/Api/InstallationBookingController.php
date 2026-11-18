<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallationBooking;
use App\Models\InstallationOffer;
use App\Models\InstallationRequest;
use App\Models\Partner;
use App\Services\CentreAvailability;
use App\Services\InstallationNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** The customer picks a day and time from what the centre offers, once the job is paid. */
class InstallationBookingController extends Controller
{
    private const MAX_RESCHEDULES = 2;
    private const CHANGE_NOTICE_HOURS = 24;

    public function __construct(private CentreAvailability $availability, private InstallationNotifier $notify)
    {
    }

    /** @return array{0: InstallationRequest, 1: Partner} */
    private function job(Request $request, int $id): array
    {
        $r = InstallationRequest::where('customer_id', $request->user()->id)->findOrFail($id);
        if (! in_array($r->status, ['paid', 'in_progress'], true) || ! $r->selected_offer_id) {
            throw ValidationException::withMessages(['request' => ['You can book a time once the job is paid and before it is finished.']]);
        }
        $centre = Partner::withoutGlobalScopes()->findOrFail(InstallationOffer::findOrFail($r->selected_offer_id)->partner_id);

        return [$r, $centre];
    }

    public function show(Request $request, int $id)
    {
        [$r, $centre] = $this->job($request, $id);

        return ['booking' => $this->present(InstallationBooking::where('request_id', $r->id)->first(), $centre)];
    }

    public function slots(Request $request, int $id)
    {
        [, $centre] = $this->job($request, $id);
        $tz = $this->availability->timezone($centre);
        $byDay = [];
        foreach ($this->availability->slots($centre) as $slot) {
            $byDay[$slot->setTimezone($tz)->toDateString()][] = $slot->toIso8601String();
        }

        return ['timezone' => $tz, 'job_hours' => $centre->job_hours, 'days' => collect($byDay)->map(fn ($times, $date) => ['date' => $date, 'times' => $times])->values()];
    }

    public function book(Request $request, int $id)
    {
        [$r, $centre] = $this->job($request, $id);
        $start = $this->start($request);

        $existing = InstallationBooking::where('request_id', $r->id)->first();
        if ($existing && $existing->status === 'booked') {
            throw ValidationException::withMessages(['starts_at' => ['This job already has a time. Change it instead.']]);
        }
        $this->availability->assertFree($centre, $start);

        $booking = InstallationBooking::updateOrCreate(['request_id' => $r->id], [
            'partner_id' => $centre->id, 'starts_at' => $start, 'ends_at' => $start->addHours($centre->job_hours), 'status' => 'booked', 'cancel_reason' => null,
        ]);
        $this->notify->centre($centre->id, 'A job was booked', "{$r->subject}: {$start->setTimezone($this->availability->timezone($centre))->format('D j M, H:i')}", "/dashboard/centre-jobs/{$r->id}");

        return response()->json(['booking' => $this->present($booking, $centre)], 201);
    }

    public function reschedule(Request $request, int $id)
    {
        [$r, $centre] = $this->job($request, $id);
        $booking = InstallationBooking::where('request_id', $r->id)->where('status', 'booked')->firstOrFail();

        if ($booking->reschedules >= self::MAX_RESCHEDULES) {
            throw ValidationException::withMessages(['starts_at' => ['A booking can be changed twice. Please contact the platform to change it again.']]);
        }
        if ($booking->starts_at->lt(now()->addHours(self::CHANGE_NOTICE_HOURS))) {
            throw ValidationException::withMessages(['starts_at' => [__('A booking can only be changed up to :n hours before it starts.', ['n' => self::CHANGE_NOTICE_HOURS])]]);
        }

        $start = $this->start($request);
        $this->availability->assertFree($centre, $start, $booking->id);
        $booking->update(['starts_at' => $start, 'ends_at' => $start->addHours($centre->job_hours), 'reschedules' => $booking->reschedules + 1]);
        $this->notify->centre($centre->id, 'A booking was moved', "{$r->subject}: {$start->setTimezone($this->availability->timezone($centre))->format('D j M, H:i')}", "/dashboard/centre-jobs/{$r->id}");

        return ['booking' => $this->present($booking->fresh(), $centre)];
    }

    private function start(Request $request): CarbonImmutable
    {
        $data = $request->validate(['starts_at' => 'required|date']);

        return CarbonImmutable::parse($data['starts_at'])->setTimezone('UTC');
    }

    /** @return array<string, mixed>|null */
    /** The time is also given in the centre's own timezone, so the customer sees it the way the list of free times showed it. */
    private function present(?InstallationBooking $b, Partner $centre): ?array
    {
        return $b ? ['timezone' => $this->availability->timezone($centre), 'starts_at' => $b->starts_at->toIso8601String(), 'ends_at' => $b->ends_at->toIso8601String(), 'status' => $b->status, 'reschedules' => $b->reschedules,
            'can_change' => $b->status === 'booked' && $b->reschedules < self::MAX_RESCHEDULES && $b->starts_at->gte(now()->addHours(self::CHANGE_NOTICE_HOURS)), 'cancel_reason' => $b->cancel_reason] : null;
    }
}
