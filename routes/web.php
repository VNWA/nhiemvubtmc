<?php

use App\Http\Controllers\Admin\EventRoomController;
use App\Http\Controllers\Admin\EventRoundController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Client\AccountController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\Sukien\EventBetController;
use App\Http\Controllers\Sukien\SukienEventRoomController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::controller(ClientController::class)->group(function () {
        Route::get('/', 'index')->name('home');
    });

    Route::prefix('tai-khoan')->name('account.')->controller(AccountController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::patch('/', 'update')->name('update');
        Route::get('vi', 'wallet')->name('wallet');
        Route::get('vi/data', 'walletData')->name('wallet.data');
    });

    Route::prefix('sukien')->name('sukien.')->group(function () {
        Route::get('/', [SukienEventRoomController::class, 'index'])->name('index');
        Route::get('{slug}', [SukienEventRoomController::class, 'show'])->name('show');
        Route::get('{slug}/rounds', [SukienEventRoomController::class, 'roundsHistory'])->name('rounds.history');
        Route::post('{slug}/bet', [EventBetController::class, 'store'])->name('bet.store');
        Route::delete('{slug}/bet', [EventBetController::class, 'destroy'])->name('bet.destroy');
    });
});

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('dashboard', function () {
            return Inertia::render('admin/Dashboard');
        })->name('dashboard');
        Route::redirect('/', '/admin/users')->name('home');

        Route::prefix('users')->name('users.')->controller(UserController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('{user}/edit', 'edit')->name('edit');
            Route::match(['put', 'patch'], '{user}', 'update')->name('update');
            Route::post('{user}/balance', 'adjustBalance')->name('balance.adjust');
            Route::delete('{user}', 'destroy')->name('destroy');
        });

        Route::prefix('sukien-rooms')->name('sukien-rooms.')->group(function () {
            Route::get('/', [EventRoomController::class, 'index'])->name('index');
            Route::get('create', [EventRoomController::class, 'create'])->name('create');
            Route::post('/', [EventRoomController::class, 'store'])->name('store');
            Route::get('{event_room}/edit', [EventRoomController::class, 'edit'])->name('edit');
            Route::get('{event_room}/manage', [EventRoomController::class, 'manage'])->name('manage');
            Route::match(['put', 'patch'], '{event_room}', [EventRoomController::class, 'update'])->name('update');
            Route::post('{event_room}/rounds/start', [EventRoundController::class, 'start'])->name('rounds.start');
            Route::post('{event_room}/rounds/{round}/end', [EventRoundController::class, 'end'])->name('rounds.end');
        });
    });

require __DIR__.'/settings.php';
