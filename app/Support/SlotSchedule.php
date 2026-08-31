<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Turns the venue's opening hours into the fixed slot grid everything else
 * agrees on: the public grid, the booking API's validation, and the
 * next-available-slot suggestion.
 *
 * The grid being fixed and hour-aligned is what makes a unique index on
 * (court, start time) a complete guarantee. If bookings could start at any
 * minute and run any length, equal start times would no longer mean "overlap"
 * and the schema alone could not settle it -- see the README on why that
 * trade-off was taken deliberately.
 */
class SlotSchedule
{
    public function __construct(
        /** @var array<string, mixed> */
        protected array $config,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('venue.schedule'));
    }

    public function slotMinutes(): int
    {
        return (int) $this->config['slot_minutes'];
    }

    public function advanceDays(): int
    {
        return (int) $this->config['advance_days'];
    }

    public function holdMinutes(): int
    {
        return (int) config('venue.hold_minutes');
    }

    public function opensAt(CarbonInterface $date): CarbonImmutable
    {
        return $this->at($date, $this->config['opens_at']);
    }

    public function closesAt(CarbonInterface $date): CarbonImmutable
    {
        return $this->at($date, $this->config['closes_at']);
    }

    /**
     * Every slot start time on a given date, in order.
     *
     * @return array<int, CarbonImmutable>
     */
    public function slotsFor(CarbonInterface $date): array
    {
        $slots = [];
        $cursor = $this->opensAt($date);
        $lastStart = $this->closesAt($date)->subMinutes($this->slotMinutes());

        while ($cursor->lessThanOrEqualTo($lastStart)) {
            $slots[] = $cursor;
            $cursor = $cursor->addMinutes($this->slotMinutes());
        }

        return $slots;
    }

    /** Does this instant sit exactly on the grid and inside opening hours? */
    public function isValidSlot(CarbonInterface $at): bool
    {
        $wanted = CarbonImmutable::instance($at);

        foreach ($this->slotsFor($wanted) as $slot) {
            if ($slot->eq($wanted)) {
                return true;
            }
        }

        return false;
    }

    public function isPeak(CarbonInterface $at): bool
    {
        $start = $this->at($at, $this->config['peak_starts_at']);
        $end = $this->at($at, $this->config['peak_ends_at']);

        return CarbonImmutable::instance($at)->betweenIncluded($start, $end->subSecond());
    }

    public function endFor(CarbonInterface $startsAt): CarbonImmutable
    {
        return CarbonImmutable::instance($startsAt)->addMinutes($this->slotMinutes());
    }

    /** The last date the public may book into. */
    public function lastBookableDate(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfDay()->addDays($this->advanceDays());
    }

    public function isWithinBookingWindow(CarbonInterface $at): bool
    {
        return CarbonImmutable::instance($at)->lessThan($this->lastBookableDate()->addDay()->startOfDay());
    }

    /**
     * Turns a `?date=` query value into a real date, defaulting to today for
     * anything missing or malformed -- a stale link pasted from a Messenger
     * thread should still land on a usable page, not a 404.
     *
     * Staff reconciling yesterday's payments need to look backward, so the
     * admin schedule passes $allowPast; the public booking grid never does.
     */
    public function resolveDate(mixed $input, bool $allowPast = false): CarbonImmutable
    {
        $today = CarbonImmutable::today();

        if (! is_string($input)) {
            return $today;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $input)->startOfDay();
        } catch (\Throwable) {
            return $today;
        }

        if (! $allowPast && $date->lessThan($today)) {
            return $today;
        }

        return $date->greaterThan($this->lastBookableDate())
            ? $this->lastBookableDate()
            : $date;
    }

    /**
     * The horizontal date picker: today plus the booking window.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dateStrip(): array
    {
        $today = CarbonImmutable::today();

        return collect(range(0, $this->advanceDays()))
            ->map(function (int $offset) use ($today) {
                $date = $today->addDays($offset);

                return [
                    'date' => $date->format('Y-m-d'),
                    'weekday' => $date->isoFormat('ddd'),
                    'day' => $date->format('j'),
                    'month' => $date->isoFormat('MMM'),
                    'is_today' => $offset === 0,
                    'is_weekend' => $date->isWeekend(),
                ];
            })->all();
    }

    protected function at(CarbonInterface $date, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return CarbonImmutable::instance($date)->setTime($hour, $minute);
    }
}
