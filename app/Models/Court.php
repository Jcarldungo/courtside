<?php

namespace App\Models;

use App\Support\SlotSchedule;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    /** @use HasFactory<\Database\Factories\CourtFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'surface',
        'description',
        'rate_peak_centavos',
        'rate_offpeak_centavos',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rate_peak_centavos' => 'integer',
            'rate_offpeak_centavos' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @param Builder<Court> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Court> $query */
    public function scopeInDisplayOrder(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /** What this court-hour costs at the given start time, in centavos. */
    public function rateFor(CarbonInterface $startsAt): int
    {
        return app(SlotSchedule::class)->isPeak($startsAt)
            ? $this->rate_peak_centavos
            : $this->rate_offpeak_centavos;
    }
}
