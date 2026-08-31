<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Services\ScheduleBoard;
use App\Support\SlotSchedule;
use App\Support\Venue;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What a court with no website needs first: who they are, what it costs, when
 * they are open, where they are, and one button that books a court.
 */
class LandingController extends Controller
{
    public function __invoke(ScheduleBoard $board, SlotSchedule $schedule): Response
    {
        $today = CarbonImmutable::today();

        return Inertia::render('Public/Landing', [
            // 'venue' is already shared globally by HandleInertiaRequests.
            'courts' => Court::active()->inDisplayOrder()->get()->map(fn (Court $court) => [
                'id' => $court->id,
                'name' => $court->name,
                'surface' => $court->surface,
                'description' => $court->description,
                'rate_peak_label' => '₱'.number_format($court->rate_peak_centavos / 100, 0),
                'rate_offpeak_label' => '₱'.number_format($court->rate_offpeak_centavos / 100, 0),
            ])->all(),

            // Proof of life on the landing page: the real state of tonight's
            // grid, so a visitor can see availability before committing to a tap.
            'tonight' => $this->tonight($board, $schedule, $today),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function tonight(ScheduleBoard $board, SlotSchedule $schedule, CarbonImmutable $today): array
    {
        $data = $board->forDate($today);

        $openPeakSlots = collect($data['courts'])
            ->flatMap(fn (array $court) => collect($court['cells'])
                ->filter(fn (array $cell) => $cell['is_peak'] && $cell['state'] === 'open')
                ->map(fn (array $cell) => $cell['label'].' · '.$court['name'])
            )
            ->values()
            ->all();

        // An empty result here means two very different things to a visitor:
        // every peak slot is sold, or peak hours are simply over for today.
        // Telling a visitor at 9:45pm that tonight is "fully booked" when the
        // truth is "peak hours ended 45 minutes ago" is a lie the code would
        // otherwise tell by accident.
        $peakEndsAt = CarbonImmutable::parse($today->format('Y-m-d').' '.config('venue.schedule.peak_ends_at'), config('app.timezone'));

        return [
            'date' => $data['date'],
            'date_label' => $data['date_label'],
            'peak_label' => Venue::timeLabel(config('venue.schedule.peak_starts_at'))
                .'–'.Venue::timeLabel(config('venue.schedule.peak_ends_at')),
            'peak_has_passed' => CarbonImmutable::now()->greaterThanOrEqualTo($peakEndsAt),
            'open_peak_slots' => $openPeakSlots,
            'open_peak_count' => count($openPeakSlots),
        ];
    }
}
