<?php

use Illuminate\Support\Facades\Route;
use Modules\Bot\Http\Controllers\V1\BaleController;

Route::prefix('v1/bale')->name('v1.bale.')->group(function () {
    Route::get('set-webhook', [BaleController::class, 'setWebhook'])->name('set-webhook');
    Route::post('webhook', [BaleController::class, 'webhook'])->name('webhook');
    Route::post('tasks/dispatch-due', [BaleController::class, 'sendDueTasks'])
        ->name('tasks.dispatch-due');
});
