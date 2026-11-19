<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InstallationBooking;
use App\Models\Partner;
use App\Models\PartnerTimeOff;
use App\Models\PartnerWorkingHour;
use App\Support\Markets\Markets;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * When a centre can take a job. Its opening hours (or its market's default week), days off, how many jobs it can do at the same time (bays) and
 * in one day decide which start times a customer is offered. Times are worked out in the centre's market timezone and stored in UTC.
 */
class CentreAvailability
{
    /** A booking is made at least this long before it starts, so the centre can prepare. */
    public const NOTICE_HOURS = 12;
    public const WINDOW_DAYS = 14;
    public const DEFAULT_OPENS = '09:00';
    public const DEFAULT_CLOSES = '18:00';

    public function __construct(private Markets $markets)
    {
    }

    public function timezone(Partner $p): string
    {
        $company = Company::withoutGlobalScopes()->find($p->company_id);

        return $this->markets->forCompany($company)?->toArray()['timezone'] ?? config('app.timezone', 'UTC');
    }

    /**
     * Opening hours by weekday (0 = Sunday). A centre that has set none works its market's default week: 09:00-18:00 except the market's weekend.
     *
     * @return array<int, array{0: string, 1: string}|null>
     */
    public function hours(Partner $p): array
    {
        $rows = PartnerWorkingHour::where('partner_id', $p->id)->get()->keyBy('weekday');
        $out = [];

        if ($rows->isEmpty()) {
            $company = Company::withoutGlobalScopes()->find($p->company_id);
            $weekend = array_map('strtolower', $this->markets->forCompany($company)?->toArray()['weekend'] ?? ['friday', 'saturday']);
            $names = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            foreach ($names as $i => $name) {
                $out[$i] = in_array($name, $weekend, true) ? null : [self::DEFAULT_OPENS, self::DEFAULT_CLOSES];
            }

            return $out;
        }

        for ($i = 0; $i <= 6; $i++) {
            $out[$i] = $rows->has($i) ? [$rows[$i]->opens, $rows[$i]->closes] : null;
        }

        return $out;
    }

    /**
     * The start times a customer can pick, hour by hour, for the next days.
     *
     * @return list<CarbonImmutable> in UTC
     */
    public function slots(Partner $p, ?CarbonImmutable $now = null, int $days = self::WINDOW_DAYS, ?int $ignoreBooking = null): array
    {
        $tz = $this->timezone($p);
        $now ??= CarbonImmutable::now('UTC');
        $earliest = $now->addHours(self::NOTICE_HOURS);
        $hours = $this->hours($p);
        $off = PartnerTimeOff::where('partner_id', $p->id)->whereBetween('date', [$now->setTimezone($tz)->toDateString(), $now->setTimezone($tz)->addDays($days)->toDateString()])->pluck('date')->map(fn ($d) => $d->toDateString())->all();
        $booked = InstallationBooking::where('partner_id', $p->id)->where('status', 'booked')->where('ends_at', '>', $now)->when($ignoreBooking, fn ($q) => $q->where('id', '!=', $ignoreBooking))->get();

        $slots = [];
        for ($d = 0; $d <= $days; $d++) {
            $day = $now->setTimezone($tz)->startOfDay()->addDays($d);
            $window = $hours[(int) $day->dayOfWeek] ?? null;
            if (! $window || in_array($day->toDateString(), $off, true)) {
                continue;
            }

            $dayStart = $day->setTimeFromTimeString($window[0]);
            $dayEnd = $day->setTimeFromTimeString($window[1]);
            $jobsToday = $booked->filter(fn ($b) => $b->starts_at->setTimezone($tz)->toDateString() === $day->toDateString())->count();
            if ($jobsToday >= $p->daily_capacity) {
                continue;
            }

            for ($start = $dayStart; $start->addHours($p->job_hours)->lte($dayEnd); $start = $start->addHour()) {
                $utc = $start->setTimezone('UTC');
                if ($utc->lt($earliest) || $this->busyBays($booked, $utc, $utc->addHours($p->job_hours)) >= $p->bays) {
                    continue;
                }
                $slots[] = $utc;
            }
        }

        return $slots;
    }

    /** The customer may only pick a start time the centre really offers. */
    public function assertFree(Partner $p, CarbonImmutable $start, ?int $ignoreBooking = null): void
    {
        foreach ($this->slots($p, null, self::WINDOW_DAYS, $ignoreBooking) as $slot) {
            if ($slot->equalTo($start)) {
                return;
            }
        }

        throw ValidationException::withMessages(['starts_at' => ['That time is not available. Choose one of the times offered.']]);
    }

    private function busyBays($booked, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $booked->filter(fn ($b) => $b->starts_at->lt($to) && $b->ends_at->gt($from))->count();
    }
}
