<?php

use Illuminate\Support\Facades\Route;
use Modules\Task\Http\Controllers\V1\TasksController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('tasks', [TasksController::class, 'getTasks']);
    Route::get('tasks/queue', [TasksController::class, 'queueTasks']);
    Route::post('tasks', [TasksController::class, 'createTask']);
    Route::patch('tasks/{taskId}', [TasksController::class, 'updateTask'])
        ->whereNumber('taskId');
    Route::delete('tasks/{taskId}', [TasksController::class, 'deleteTask'])
        ->whereNumber('taskId');
});
