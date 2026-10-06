<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\V1\AuthController;

Route::prefix('auth')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::post('create_user', [AuthController::class, 'createUser']);
        Route::patch('update_user', [AuthController::class, 'updateUser']);
        Route::delete('delete_user', [AuthController::class, 'deleteUser']);
    });
});
