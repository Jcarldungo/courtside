<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Court;
use App\Support\SlotSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds the courts x slots grid that both the public booking page and the
 * admin schedule render.
 *
 * One query for the courts and one for the day's live bookings, then the grid is
 * assembled in memory -- rather than a query per cell, which for four courts
 * across sixteen slots would be sixty-four round trips per page view on a host
 * where every one of them is measured.
 */
class ScheduleBoard
{
    public function __construct(protected SlotSchedule $schedule) {}

    /**
     * @return array<string, mixed>
     */
    public function forDate(CarbonInterface $date, bool $forStaff = false): array
    {
        $slots = $this->schedule->slotsFor($date);

        $courts = Court::query()
            ->unless($forStaff, fn ($query) => $query->active())
            ->inDisplayOrder()
            ->get();

        // Keyed by "courtId|HH:mm" so each cell is an array lookup, not a query.
        $live = Booking::query()
            ->live()
            ->forDate($date)
            ->get()
            ->keyBy(fn (Booking $booking) => $booking->court_id.'|'.$booking->starts_at->format('H:i'));

        return [
            'date' => $date->format('Y-m-d'),
            'date_label' => $date->isoFormat('ddd D MMM'),
            'is_today' => $date->isToday(),
            'slots' => $this->slotHeadings($slots),
            'courts' => $courts->map(fn (Court $court) => [
                'id' => $court->id,
                'name' => $court->name,
                'surface' => $court->surface,
                'is_active' => $court->is_active,
                'rate_peak_label' => $this->peso($court->rate_peak_centavos),
                'rate_offpeak_label' => $this->peso($court->rate_offpeak_centavos),
                'cells' => collect($slots)
                    ->map(fn ($slot) => $this->cell($court, $slot, $live, $forStaff))
                    ->all(),
            ])->all(),
            'summary' => $forStaff ? $this->summary($live) : null,
        ];
    }

    /**
     * @param  array<int, CarbonInterface>  $slots
     * @return array<int, array<string, mixed>>
     */
    protected function slotHeadings(array $slots): array
    {
        return collect($slots)->map(fn (CarbonInterface $slot) => [
            'start' => $slot->format('H:i'),
            'label' => $slot->format('g:ia'),
            'short_label' => $slot->format('ga'),
            'is_peak' => $this->schedule->isPeak($slot),
        ])->all();
    }

    /**
     * One cell of the grid.
     *
     * The public payload is deliberately thin: a taken slot says only that it is
     * taken. Who booked it, their mobile number and what they paid are the
     * venue's business, and a booking page that leaks them is a booking page a
     * court owner cannot use.
     *
     * @param  Collection<string, Booking>  $live
     * @return array<string, mixed>
     */
    protected function cell(Court $court, CarbonInterface $slot, Collection $live, bool $forStaff): array
    {
        $booking = $live->get($court->id.'|'.$slot->format('H:i'));
        $isPeak = $this->schedule->isPeak($slot);
        $price = $isPeak ? $court->rate_peak_centavos : $court->rate_offpeak_centavos;

        $cell = [
            'slot' => $slot->format('H:i'),
            'starts_at' => $slot->format('Y-m-d H:i'),
            'label' => $slot->format('g:ia'),
            'is_peak' => $isPeak,
            'price_label' => $this->peso($price),
            'state' => $this->state($court, $slot, $booking, $forStaff),
        ];

        if ($forStaff && $booking) {
            $cell['booking'] = [
                'id' => $booking->id,
                'reference' => $booking->reference,
                'kind' => $booking->kind->value,
                'status' => $booking->status->value,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'amount_label' => $booking->amountLabel(),
                'has_proof' => $booking->hasProof(),
                'notes' => $booking->notes,
            ];
        }

        return $cell;
    }

    protected function state(Court $court, CarbonInterface $slot, ?Booking $booking, bool $forStaff): string
    {
        if ($booking?->isMaintenance()) {
            return 'blocked';
        }

        if ($booking) {
            // Staff need to tell an unpaid hold from money in the bank.
            return $forStaff ? $booking->status->value : 'taken';
        }

        if (! $court->is_active) {
            return 'closed';
        }

        return $slot->isPast() ? 'past' : 'open';
    }

    /**
     * @param  Collection<string, Booking>  $live
     * @return array<string, mixed>
     */
    protected function summary(Collection $live): array
    {
        $bookings = $live->filter(fn (Booking $b) => ! $b->isMaintenance());

        return [
            'booked' => $bookings->count(),
            'awaiting_payment' => $bookings->filter(fn (Booking $b) => $b->status->value === 'pending')->count(),
            'awaiting_verification' => $bookings->filter(fn (Booking $b) => $b->status->value === 'pending' && $b->hasProof())->count(),
            'blocked' => $live->filter(fn (Booking $b) => $b->isMaintenance())->count(),
            'expected_centavos' => $bookings
                ->filter(fn (Booking $b) => $b->status->value === 'confirmed')
                ->sum('amount_centavos'),
            'expected_label' => $this->peso(
                $bookings->filter(fn (Booking $b) => $b->status->value === 'confirmed')->sum('amount_centavos')
            ),
        ];
    }

    protected function peso(int $centavos): string
    {
        return '₱'.number_format($centavos / 100, 0);
    }
}
