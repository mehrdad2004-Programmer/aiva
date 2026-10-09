<?php

namespace Modules\Task\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Task\Http\Requests\V1\CreateTaskTypeRequest;
use Modules\Task\Http\Requests\V1\DeleteTaskTypeRequest;
use Modules\Task\Http\Requests\V1\GetTaskTypesRequest;
use Modules\Task\Http\Requests\V1\UpdateTaskTypeRequest;
use Modules\Task\Services\V1\TaskTypesService;

class TaskTypesController extends Controller
{
    public function getTaskTypes(GetTaskTypesRequest $request): JsonResponse
    {
        try {
            $result = TaskTypesService::getTaskTypes(
                (string) $request->attributes->get('bale_user_id')
            );

            return response()->json($result, $result['statuscode']);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function createTaskType(CreateTaskTypeRequest $request): JsonResponse
    {
        try {
            $result = TaskTypesService::createTaskType(
                $request->validated(),
                (string) $request->attributes->get('bale_user_id')
            );

            return response()->json($result, $result['statuscode']);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function updateTaskType(UpdateTaskTypeRequest $request, int $taskTypeId): JsonResponse
    {
        try {
            $result = TaskTypesService::updateTaskType(
                $taskTypeId,
                $request->validated(),
                (string) $request->attributes->get('bale_user_id')
            );

            return response()->json($result, $result['statuscode']);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function deleteTaskType(DeleteTaskTypeRequest $request, int $taskTypeId): JsonResponse
    {
        try {
            $result = TaskTypesService::deleteTaskType(
                $taskTypeId,
                (string) $request->attributes->get('bale_user_id')
            );

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
