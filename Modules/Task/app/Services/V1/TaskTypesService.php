<?php

namespace Modules\Task\Services\V1;

use Modules\Task\Models\V1\TaskTypesModel;

class TaskTypesService
{
    public static function getTaskTypes(): array
    {
        try {
            $taskTypes = TaskTypesModel::query()->paginate(10);

            if ($taskTypes->isEmpty()) {
                return [
                    'msg' => 'task types not found',
                    'data' => $taskTypes,
                    'statuscode' => 404,
                ];
            }

            return [
                'msg' => 'task types found',
                'data' => $taskTypes,
                'statuscode' => 200,
            ];
        } catch (\Throwable $e) {
            return [
                'msg' => $e->getMessage().' at line '.$e->getLine(),
                'statuscode' => 500,
            ];
        }
    }

    public static function createTaskType(array $data): array
    {
        try {
            $taskType = TaskTypesModel::query()->create($data);

            return [
                'msg' => 'task type created',
                'data' => $taskType,
                'statuscode' => 201,
            ];
        } catch (\Throwable $e) {
            return [
                'msg' => $e->getMessage().' at line '.$e->getLine(),
                'statuscode' => 500,
            ];
        }
    }

    public static function updateTaskType(int $taskTypeId, array $data): array
    {
        try {
            $taskType = TaskTypesModel::query()->find($taskTypeId);

            if (! $taskType) {
                return [
                    'msg' => 'task type not found',
                    'statuscode' => 404,
                ];
            }

            $taskType->update($data);

            return [
                'msg' => 'task type updated',
                'data' => $taskType->refresh(),
                'statuscode' => 200,
            ];
        } catch (\Throwable $e) {
            return [
                'msg' => $e->getMessage().' at line '.$e->getLine(),
                'statuscode' => 500,
            ];
        }
    }

    public static function deleteTaskType(int $taskTypeId): array
    {
        try {
            $taskType = TaskTypesModel::query()->find($taskTypeId);

            if (! $taskType) {
                return [
                    'msg' => 'task type not found',
                    'statuscode' => 404,
                ];
            }

            $taskType->delete();

            return [
                'msg' => 'task type deleted',
                'statuscode' => 200,
            ];
        } catch (\Throwable $e) {
            return [
                'msg' => $e->getMessage().' at line '.$e->getLine(),
                'statuscode' => 500,
            ];
        }
    }
}
