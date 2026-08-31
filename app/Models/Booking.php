<?php

namespace App\Models;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

    /**
     * `active_slot_at` is absent by design: it is a stored generated column
     * maintained by the database. Nothing in application code may write it.
     */
    protected $fillable = [
        'reference',
        'court_id',
        'kind',
        'status',
        'starts_at',
        'ends_at',
        'customer_name',
        'customer_phone',
        'amount_centavos',
        'is_peak',
        'hold_expires_at',
        'payment_reference',
        'payment_proof_path',
        'proof_uploaded_at',
        'confirmed_at',
        'confirmed_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'kind' => BookingKind::class,
            'status' => BookingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active_slot_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'proof_uploaded_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'amount_centavos' => 'integer',
            'is_peak' => 'boolean',
        ];
    }

    /**
     * Ambiguity-free reference code: no O/0, no I/1, because customers read
     * these out over the phone and staff type them back in.
     */
    public static function newReference(): string
    {
        // Deliberately excludes O/0, I/1, S/5, B/8 and Z/2.
        $alphabet = 'ACDEFGHJKLMNPQRTUVWXY34679';
        $code = '';

        for ($i = 0; $i < 5; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'CS-'.$code;
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Rows that currently occupy a court slot.
     *
     * Filtering on the generated column rather than re-listing statuses keeps
     * this query answering exactly the question the unique index enforces.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNotNull('active_slot_at');
    }

    /** @param Builder<Booking> $query */
    public function scopeAwaitingPayment(Builder $query): void
    {
        $query->where('status', BookingStatus::Pending);
    }

    /** @param Builder<Booking> $query */
    public function scopeForDate(Builder $query, CarbonInterface $date): void
    {
        $query->whereBetween('starts_at', [
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay(),
        ]);
    }

    /** @param Builder<Booking> $query */
    public function scopeCustomerBookings(Builder $query): void
    {
        $query->where('kind', BookingKind::Booking);
    }

    /** @param Builder<Booking> $query */
    public function scopeMaintenance(Builder $query): void
    {
        $query->where('kind', BookingKind::Maintenance);
    }

    /**
     * Pending holds whose timer has run out. Used by both the queued job and
     * the scheduled sweeper that backs it up.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeStale(Builder $query): void
    {
        $query->where('status', BookingStatus::Pending)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now());
    }

    public function isMaintenance(): bool
    {
        return $this->kind === BookingKind::Maintenance;
    }

    public function hasProof(): bool
    {
        return $this->payment_proof_path !== null;
    }

    public function isAwaitingProof(): bool
    {
        return $this->status === BookingStatus::Pending && ! $this->hasProof();
    }

    /** Seconds left on the payment hold, floored at zero. */
    public function holdSecondsRemaining(): int
    {
        if ($this->status !== BookingStatus::Pending || ! $this->hold_expires_at) {
            return 0;
        }

        return max(0, now()->diffInSeconds($this->hold_expires_at, false));
    }

    public function amountLabel(): string
    {
        return '₱'.number_format($this->amount_centavos / 100, 0);
    }

    public function timeLabel(): string
    {
        return $this->starts_at->format('g:ia').' – '.$this->ends_at->format('g:ia');
    }
}
