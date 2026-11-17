<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstallationBooking;
use App\Models\InstallationRequest;
use App\Models\Partner;
use App\Models\PartnerTimeOff;
use App\Models\PartnerWorkingHour;
use App\Models\User;
use App\Services\CentreAvailability;
use App\Services\InstallationNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A centre's own rules: how far it goes, how much it takes, when it works, and its calendar of booked jobs. */
class CentreSettingsController extends Controller
{
    public const WORK_TYPES = ['install', 'repair', 'maintenance'];

    public function __construct(private CentreAvailability $availability)
    {
    }

    private function partner(Request $request): Partner
    {
        abort_unless($request->user()->partner_id, 422, 'Your account is not linked to a partner yet.');

        return Partner::withoutGlobalScopes()->findOrFail($request->user()->partner_id);
    }

    public function show(Request $request)
    {
        return $this->present($this->partner($request));
    }

    public function update(Request $request)
    {
        $p = $this->partner($request);
        $data = $request->validate([
            'service_radius_km' => 'sometimes|integer|min:1|max:100',
            'daily_capacity' => 'sometimes|integer|min:1|max:50',
            'bays' => 'sometimes|integer|min:1|max:20',
            'job_hours' => 'sometimes|integer|min:1|max:12',
            'min_job_price' => 'sometimes|nullable|numeric|min:0|max:10000000',
            'accepts_requests' => 'sometimes|boolean',
            'work_types' => 'sometimes|nullable|array',
            'work_types.*' => 'in:'.implode(',', self::WORK_TYPES),
        ]);
        if (array_key_exists('work_types', $data)) {
            $data['work_types'] = $data['work_types'] ? array_values(array_unique($data['work_types'])) : null;
        }

        $p->update($data);
        \App\Support\Audit::record('centre.settings_changed', $p, array_keys($data), $request->user());

        return $this->present($p->fresh());
    }

    /** The week in full: send the days the centre is open, every other day is closed. */
    public function hours(Request $request)
    {
        $p = $this->partner($request);
        $data = $request->validate([
            'days' => 'present|array|max:7',
            'days.*.weekday' => 'required|integer|between:0,6|distinct',
            'days.*.opens' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'days.*.closes' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);

        foreach ($data['days'] as $d) {
            if ($d['closes'] <= $d['opens']) {
                throw ValidationException::withMessages(['days' => ['A centre must close after it opens.']]);
            }
        }

        DB::transaction(function () use ($p, $data) {
            PartnerWorkingHour::where('partner_id', $p->id)->delete();
            foreach ($data['days'] as $d) {
                PartnerWorkingHour::create(['partner_id' => $p->id, 'weekday' => $d['weekday'], 'opens' => $d['opens'], 'closes' => $d['closes']]);
            }
        });

        return $this->present($p);
    }

    public function addTimeOff(Request $request)
    {
        $p = $this->partner($request);
        $data = $request->validate(['date' => 'required|date|after_or_equal:today', 'reason' => 'nullable|string|max:120']);
        PartnerTimeOff::updateOrCreate(['partner_id' => $p->id, 'date' => $data['date']], ['reason' => isset($data['reason']) ? trim(strip_tags($data['reason'])) : null]);

        return response()->json($this->present($p), 201);
    }

    public function removeTimeOff(Request $request, int $id)
    {
        $p = $this->partner($request);
        PartnerTimeOff::where('partner_id', $p->id)->whereKey($id)->delete();

        return $this->present($p);
    }

    /** Booked jobs in a range of days, for the centre's calendar. */
    public function calendar(Request $request)
    {
        $p = $this->partner($request);
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = CarbonImmutable::parse($data['from'] ?? now())->startOfDay();
        $to = CarbonImmutable::parse($data['to'] ?? $from->addDays(14))->endOfDay();

        return InstallationBooking::where('partner_id', $p->id)->where('status', 'booked')->whereBetween('starts_at', [$from, $to])->with('request')->orderBy('starts_at')->get()->map(function ($b) {
            $r = $b->request;

            return ['id' => $b->id, 'job_id' => $r?->id, 'starts_at' => $b->starts_at->toIso8601String(), 'ends_at' => $b->ends_at->toIso8601String(), 'subject' => $r?->subject, 'parts' => $r?->parts,
                'work_type' => $r?->work_type, 'customer' => $r ? explode(' ', trim((string) User::whereKey($r->customer_id)->value('name')))[0] : null];
        })->values();
    }

    /** The centre cannot do the booked time: the customer is told to choose another. */
    public function cancelBooking(Request $request, int $id)
    {
        $p = $this->partner($request);
        $data = $request->validate(['reason' => 'required|string|max:200']);
        $booking = InstallationBooking::where('partner_id', $p->id)->where('request_id', $id)->where('status', 'booked')->firstOrFail();
        $booking->update(['status' => 'cancelled', 'cancel_reason' => trim(strip_tags($data['reason']))]);

        $job = InstallationRequest::find($booking->request_id);
        app(InstallationNotifier::class)->user($job->customer_id, 'The centre needs another time', 'The centre could not keep your booked time. Please choose another time.', "/dashboard/installations/{$job->id}");

        return response()->json(['status' => 'cancelled']);
    }

    /** @return array<string, mixed> */
    private function present(Partner $p): array
    {
        $p->refresh();

        return [
            'service_radius_km' => $p->service_radius_km, 'daily_capacity' => $p->daily_capacity, 'bays' => $p->bays, 'job_hours' => $p->job_hours,
            'min_job_price' => $p->min_job_price !== null ? (float) $p->min_job_price : null, 'accepts_requests' => (bool) $p->accepts_requests,
            'work_types' => $p->work_types ?? self::WORK_TYPES, 'timezone' => $this->availability->timezone($p),
            'hours' => collect($this->availability->hours($p))->map(fn ($w, $day) => $w ? ['weekday' => $day, 'opens' => $w[0], 'closes' => $w[1]] : null)->filter()->values(),
            'time_off' => PartnerTimeOff::where('partner_id', $p->id)->where('date', '>=', now()->toDateString())->orderBy('date')->get()->map(fn ($t) => ['id' => $t->id, 'date' => $t->date->toDateString(), 'reason' => $t->reason])->values(),
        ];
    }
}
