<?php

use Illuminate\Support\Facades\Route;
use Modules\Bot\Http\Controllers\BotController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('bots', BotController::class)->names('bot');
});
