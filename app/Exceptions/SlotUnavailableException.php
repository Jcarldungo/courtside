<?php

namespace App\Exceptions;

use App\Models\Court;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Thrown when the database refused an insert because the court-slot was already
 * taken -- i.e. this request lost the race.
 *
 * It deliberately carries the next open slot on the same court. Losing a race
 * for a 7pm court is only a dead end if the app says "unavailable" and stops;
 * carrying the alternative turns the failure into one more tap.
 */
class SlotUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly Court $court,
        public readonly CarbonImmutable $requested,
        public readonly ?CarbonImmutable $nextAvailable = null,
    ) {
        parent::__construct(sprintf(
            '%s at %s was just taken.',
            $court->name,
            $requested->format('g:ia')
        ));
    }

    /**
     * The shape the booking endpoint returns with a 409.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->publicMessage(),
            'court' => [
                'id' => $this->court->id,
                'name' => $this->court->name,
            ],
            'requested_at' => $this->requested->toIso8601String(),
            'next_available_at' => $this->nextAvailable?->toIso8601String(),
            'next_available_label' => $this->nextAvailable?->format('g:ia'),
        ];
    }

    public function publicMessage(): string
    {
        if (! $this->nextAvailable) {
            return sprintf(
                '%s at %s was just taken. Nothing else is open on that court today — try another court.',
                $this->court->name,
                $this->requested->format('g:ia')
            );
        }

        return sprintf(
            '%s at %s was just taken. The next open slot on that court is %s.',
            $this->court->name,
            $this->requested->format('g:ia'),
            $this->nextAvailable->isSameDay($this->requested)
                ? $this->nextAvailable->format('g:ia')
                : $this->nextAvailable->format('g:ia \o\n D j M')
        );
    }
}
