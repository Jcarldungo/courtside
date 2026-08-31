<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\ScheduleBoard;
use App\Support\SlotSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingBoardController extends Controller
{
    public function __invoke(Request $request, ScheduleBoard $board, SlotSchedule $schedule): Response
    {
        $date = $schedule->resolveDate($request->query('date'));

        return Inertia::render('Public/Book', [
            'board' => $board->forDate($date),
            'dates' => $schedule->dateStrip(),
        ]);
    }
}
