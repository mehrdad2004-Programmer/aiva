<?php

namespace Modules\Task\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Task\Http\Requests\V1\CreateTaskRequest;
use Modules\Task\Http\Requests\V1\DeleteTaskRequest;
use Modules\Task\Http\Requests\V1\GetTasksRequest;
use Modules\Task\Http\Requests\V1\QueueTasksRequest;
use Modules\Task\Http\Requests\V1\UpdateTaskRequest;
use Modules\Task\Services\V1\TasksService;

class TasksController extends Controller
{
    public function updateTask(UpdateTaskRequest $request, int $taskId): JsonResponse
    {
        try {
            $result = TasksService::updateTask(
                $taskId,
                $request->validated()
            );

            return response()->json($result, $result['statuscode']);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function getTasks(GetTasksRequest $request): JsonResponse
    {
        try {
            $result = TasksService::getTasks($request->validated());

            return response()->json($result, $result['statuscode']);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function createTask(CreateTaskRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $result = TasksService::createTask(
                $data,
                $data
            );

            return response()->json($result, $result['statuscode']);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function deleteTask(DeleteTaskRequest $request, int $taskId): JsonResponse
    {
        try {
            $result = TasksService::deleteTask($taskId);

            return response()->json($result, $result['statuscode']);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function queueTasks(QueueTasksRequest $request): JsonResponse
    {
        try {
            $result = TasksService::queueTasks();

            return response()->json($result, $result['statuscode']);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    private function errorResponse(\Throwable $e): JsonResponse
    {
        return response()->json([
            'msg' => $e->getMessage().' at line '.$e->getLine(),
            'statuscode' => 500,
        ], 500);
    }
}
