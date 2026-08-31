<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\BookingBoardController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\PaymentProofController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
| Staff
|--------------------------------------------------------------------------
*/

// Replaced by the real admin schedule in the staff phase; Breeze points its
// post-login redirect at this name.
Route::get('/dashboard', fn () => Inertia::render('Dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
