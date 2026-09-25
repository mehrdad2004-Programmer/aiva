<?php

use Illuminate\Support\Facades\Route;
use Modules\Task\Http\Controllers\V1\TasksController;

Route::prefix('tasks')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::get('/', [TasksController::class, 'getTasks']);
        Route::get('/queue', [TasksController::class, 'queueTasks']);
        Route::post('/', [TasksController::class, 'createTask']);
        Route::patch('/{taskId}', [TasksController::class, 'updateTask'])
            ->whereNumber('taskId');
        Route::delete('/{taskId}', [TasksController::class, 'deleteTask'])
            ->whereNumber('taskId');
    });
});
