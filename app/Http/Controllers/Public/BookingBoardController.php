<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\ScheduleBoard;
use App\Support\SlotSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingBoardController extends Controller
{
    public function __invoke(Request $request, ScheduleBoard $board, SlotSchedule $schedule): Response
    {
        $date = $this->resolveDate($request->query('date'), $schedule);

        return Inertia::render('Public/Book', [
            'board' => $board->forDate($date),
            'dates' => $this->dateStrip($schedule),
        ]);
    }

    /**
     * A bad or out-of-range ?date= lands on today rather than a 404. Someone
     * pasting a stale link from a Messenger thread should still get a usable page.
     */
    protected function resolveDate(mixed $input, SlotSchedule $schedule): CarbonImmutable
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

        if ($date->lessThan($today)) {
            return $today;
        }

        return $date->greaterThan($schedule->lastBookableDate())
            ? $schedule->lastBookableDate()
            : $date;
    }

    /**
     * The horizontal date picker: today plus the booking window.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function dateStrip(SlotSchedule $schedule): array
    {
        $today = CarbonImmutable::today();

        return collect(range(0, $schedule->advanceDays()))
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
}
