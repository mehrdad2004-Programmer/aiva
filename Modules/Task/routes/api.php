<?php

use Illuminate\Support\Facades\Route;
use Modules\Task\Http\Controllers\V1\TasksController;
use Modules\Task\Http\Controllers\V1\TaskTypesController;
use Modules\Task\Http\Middleware\ValidateBaleMiniApp;

Route::middleware(ValidateBaleMiniApp::class)->prefix('tasks')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::get('/task-types', [TaskTypesController::class, 'getTaskTypes']);
        Route::post('/task-types', [TaskTypesController::class, 'createTaskType']);
        Route::patch('/task-types/{taskTypeId}', [TaskTypesController::class, 'updateTaskType'])
            ->whereNumber('taskTypeId');
        Route::delete('/task-types/{taskTypeId}', [TaskTypesController::class, 'deleteTaskType'])
            ->whereNumber('taskTypeId');

        Route::get('/', [TasksController::class, 'getTasks']);
        Route::get('/queue', [TasksController::class, 'queueTasks']);
        Route::post('/', [TasksController::class, 'createTask']);
        Route::patch('/{taskId}', [TasksController::class, 'updateTask'])
            ->whereNumber('taskId');
        Route::delete('/{taskId}', [TasksController::class, 'deleteTask'])
            ->whereNumber('taskId');
    });
});
