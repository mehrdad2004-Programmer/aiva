<?php

namespace Modules\Task\Services\V1;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Task\Models\V1\ReminderModel;
use Modules\Task\Models\V1\TasksModel;

class TasksService
{
    public static function updateTask(int $taskId, array $data): array
    {
        try {
            DB::beginTransaction();

            $task = TasksModel::query()->find($taskId);

            if (! $task) {
                DB::rollBack();

                return [
                    'msg' => 'task not found',
                    'statuscode' => 404,
                ];
            }

            $reminder = $task->reminders()->first();

            if (! $reminder) {
                DB::rollBack();

                return [
                    'msg' => 'reminder not found',
                    'statuscode' => 404,
                ];
            }

            $task->update(Arr::only($data, [
                'title',
                'chat_id',
                'type',
                'comments',
                'status',
            ]));

            $reminder->update(Arr::only($data, [
                'chat_id',
                'remind_at',
            ]));

            DB::commit();

            return [
                'msg' => 'task updated',
                'statuscode' => 200,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'msg' => $e->getMessage().' at line '.$e->getLine(),
                'statuscode' => 500,
            ];
        }
    }

    public static function queueTasks(): array
    {
        try {
            $tasks = TasksModel::query()
                ->where('status', '1')
                ->withWhereHas(
                    'reminders',
                    fn ($query) => $query->whereNull('deleted_at')
                )
                ->paginate(10);

            if ($tasks->isEmpty()) {
                return [
                    'data' => $tasks,
                    'statuscode' => 404,
                ];
            }

            return [
                'data' => $tasks,
                'statuscode' => 200,
            ];
        } catch (\Throwable $e) {
            return [
                'msg' => $e->getMessage().' at line '.$e->getLine(),
                'statuscode' => 500,
            ];
        }
    }

    public static function deleteTask(int $taskId): array
    {
        try {
            DB::beginTransaction();

            $task = TasksModel::query()->find($taskId);

            if (! $task) {
                DB::rollBack();

                return [
                    'msg' => 'task not found',
                    'statuscode' => 404,
                ];
            }

            $deletedAt = now();

            $task->reminders()->withTrashed()->update([
                'deleted_at' => $deletedAt,
            ]);

            $task->status = '3';
            $task->deleted_at = $deletedAt;
            $task->save();

            DB::commit();

            return [
                'msg' => 'task deleted',
                'statuscode' => 200,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'msg' => $e->getMessage().' at line '.$e->getLine(),
                'statuscode' => 500,
            ];
        }
    }

    public static function getTasks(array $filters = []): array
    {
        try {
            $tasks = TasksModel::query()
                ->with('reminders')
                ->when(
                    isset($filters['title']) && $filters['title'] !== '',
                    fn ($query) => $query->where(
                        'tasks.title',
                        'like',
                        '%'.$filters['title'].'%'
                    )
                )
                ->when(
                    isset($filters['type']) && $filters['type'] !== '',
                    fn ($query) => $query->where('tasks.type', $filters['type'])
                )
                ->when(
                    isset($filters['status']) && $filters['status'] !== '',
                    fn ($query) => $query->where('tasks.status', $filters['status'])
                )
                ->paginate(10);

            if ($tasks->isEmpty()) {
                return [
                    'msg' => 'not found',
                    'statuscode' => 404,
                ];
            }

            return [
                'msg' => 'found',
                'data' => $tasks,
                'statuscode' => 200,
            ];

        } catch (\Throwable $e) {
            return [
                'msg' => $e->getMessage().' at line '.$e->getLine(),
                'statuscode' => 500,
            ];
        }
    }

    public static function createTask(array $task_array, array $reminder_array): array
    {
        try {

            DB::beginTransaction();

            TasksModel::create($task_array);
            ReminderModel::create($reminder_array);

            DB::commit();

            return [
                'msg' => 'inserted',
                'statuscode' => 201,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'msg' => $e->getMessage().' at line '.$e->getLine(),
                'statuscode' => 500,
            ];

        }
    }
}
