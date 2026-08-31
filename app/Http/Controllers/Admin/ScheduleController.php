<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ScheduleBoard;
use App\Support\SlotSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The staff view of the same grid the public books from -- same
 * ScheduleBoard, same date math, run with forStaff: true so each cell carries
 * the customer's name, number, and payment status instead of just "taken".
 */
class ScheduleController extends Controller
{
    public function __invoke(Request $request, ScheduleBoard $board, SlotSchedule $schedule): Response
    {
        // Reconciling yesterday's payments is routine counter work, so staff
        // -- unlike the public grid -- may look backward.
        $date = $schedule->resolveDate($request->query('date'), allowPast: true);

        return Inertia::render('Admin/Schedule', [
            'board' => $board->forDate($date, forStaff: true),
            'dates' => $schedule->dateStrip(),
        ]);
    }
}
