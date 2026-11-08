<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [ChatController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/contacts-summary', [ChatController::class, 'contactsSummary'])->name('chat.contacts');
    Route::get('/dashboard/{receiver_id}', [ChatController::class, 'show'])->whereNumber('receiver_id')->name('chat.show');
    Route::get('/dashboard/{receiver_id}/messages', [ChatController::class, 'fetchMessages'])->whereNumber('receiver_id')->name('chat.messages');
    Route::post('/dashboard/{receiver_id}', [ChatController::class, 'sendMessage'])->whereNumber('receiver_id')->name('chat.send');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
