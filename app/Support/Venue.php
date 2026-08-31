<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Reads config/venue.php and hands the front end one shape.
 *
 * Components never call config() and never hardcode a venue's name, phone
 * number or opening hours, which is what makes a re-skin a config edit rather
 * than a search-and-replace through JSX.
 */
class Venue
{
    /** @return array<string, mixed> */
    public static function toArray(): array
    {
        $schedule = app(SlotSchedule::class);

        return [
            'name' => config('venue.name'),
            'short_name' => config('venue.short_name'),
            'tagline' => config('venue.tagline'),

            // "court"/"courts" here, "room"/"rooms" at a KTV bar: the interface
            // copy follows the config so the system is not pickleball-only.
            'unit' => config('venue.unit'),
            'units' => config('venue.units'),

            'contact' => config('venue.contact'),
            'location' => config('venue.location'),
            'payment' => config('venue.payment'),
            'amenities' => config('venue.amenities'),

            'photos' => [
                'hero' => asset(config('venue.photos.hero')),
                'gallery' => collect(config('venue.photos.gallery'))
                    ->map(fn (array $photo) => [
                        'src' => asset($photo['src']),
                        'alt' => $photo['alt'],
                    ])->all(),
            ],

            'hours' => [
                'opens_at' => config('venue.schedule.opens_at'),
                'closes_at' => config('venue.schedule.closes_at'),
                'label' => self::timeLabel(config('venue.schedule.opens_at'))
                    .' – '.self::timeLabel(config('venue.schedule.closes_at')).', daily',
                'peak_label' => self::timeLabel(config('venue.schedule.peak_starts_at'))
                    .' – '.self::timeLabel(config('venue.schedule.peak_ends_at')),
            ],

            'booking' => [
                'slot_minutes' => $schedule->slotMinutes(),
                'hold_minutes' => $schedule->holdMinutes(),
                'advance_days' => $schedule->advanceDays(),
            ],
        ];
    }

    /** '18:00' -> '6:00pm' */
    public static function timeLabel(string $time): string
    {
        return Carbon::createFromFormat('H:i', $time)->format('g:ia');
    }

    /** Colour tokens, emitted as CSS custom properties so a re-skin needs no rebuild. */
    public static function cssVariables(): string
    {
        return collect(config('venue.theme'))
            ->map(fn (string $value, string $token) => "--venue-{$token}: {$value};")
            ->implode(' ');
    }
}
