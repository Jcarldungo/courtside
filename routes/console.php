<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Holds are released by a delayed queued job. This sweeper runs alongside it,
| every minute, as the thing that keeps working when the queue worker does not.
|
| withoutOverlapping() matters on a cheap host, where a slow minute can leave
| two copies of this command racing each other over the same rows.
|
*/

Schedule::command('courtside:release-holds')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
