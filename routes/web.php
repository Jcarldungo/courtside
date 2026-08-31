<?php

use App\Http\Controllers\Admin\BookingActionController;
use App\Http\Controllers\Admin\MaintenanceBlockController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\BookingBoardController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\PaymentProofController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
|
| No account, no login. A court's customers arrive from a Facebook post on an
| Android phone and will not register to reserve an hour, so the booking
| reference in the URL is their credential for the one booking it names.
|
*/

Route::get('/', LandingController::class)->name('home');
Route::get('/book', BookingBoardController::class)->name('book');

Route::post('/bookings', [BookingController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('bookings.store');

Route::get('/b/{booking:reference}', [BookingController::class, 'show'])->name('bookings.show');

Route::post('/b/{booking:reference}/proof', [PaymentProofController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('bookings.proof.store');

/*
|--------------------------------------------------------------------------
| Demo
|--------------------------------------------------------------------------
|
| Both 404 unless venue.demo_mode is true (see DemoController). A court owner
| evaluating this system will never create an account, so 'enter' signs them
| into the admin view directly, and 'reset' hands them a fresh, current week
| of realistic data whenever the one they're looking at goes stale.
|
*/

Route::get('/demo/enter', [DemoController::class, 'enter'])->name('demo.enter');
Route::post('/demo/reset', [DemoController::class, 'reset'])
    ->middleware('throttle:10,1')
    ->name('demo.reset');

/*
|--------------------------------------------------------------------------
| Staff
|--------------------------------------------------------------------------
|
| Every account in this system's users table is an owner or staff login
| provisioned with `php artisan courtside:staff` -- there is no public
| registration and no third role -- so `auth` alone is the correct gate for
| all of it. Nothing here checks for owner specifically: v1 gives staff and
| owner the same admin capabilities.
|
*/

// Named 'dashboard' because Breeze's login redirect targets that name; the
// URL and the name don't have to match.
Route::get('/admin', ScheduleController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::post('/bookings/{booking}/confirm', [BookingActionController::class, 'confirm'])->name('bookings.confirm');
    Route::post('/bookings/{booking}/reject', [BookingActionController::class, 'reject'])->name('bookings.reject');
    Route::post('/bookings/{booking}/cancel', [BookingActionController::class, 'cancel'])->name('bookings.cancel');
    Route::post('/maintenance', [MaintenanceBlockController::class, 'store'])->name('maintenance.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
