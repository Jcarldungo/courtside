<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaintenanceBlockRequest;
use App\Models\Court;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;

class MaintenanceBlockController extends Controller
{
    public function __construct(protected BookingService $bookings) {}

    public function store(StoreMaintenanceBlockRequest $request): RedirectResponse
    {
        $court = Court::findOrFail($request->integer('court_id'));

        $this->bookings->block(
            court: $court,
            startsAt: $request->slotStartsAt(),
            staff: $request->user(),
            reason: $request->input('reason'),
        );

        return back()->with('status', 'court-blocked');
    }
}
