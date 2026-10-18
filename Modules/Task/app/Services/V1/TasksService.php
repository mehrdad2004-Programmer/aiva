<?php

namespace Modules\Task\Services\V1;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Task\Models\V1\ReminderModel;
use Modules\Task\Models\V1\TasksModel;

class TasksService
{
    public static function updateTask(int $taskId, array $data, string $chatId): array
    {
        try {
            DB::beginTransaction();

            $task = TasksModel::query()->where('chat_id', $chatId)->find($taskId);

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
                'type',
                'comments',
                'status',
            ]));

            $reminderData = Arr::only($data, [
                'remind_at',
            ]);

            if (array_key_exists('remind_at', $reminderData)) {
                $reminderData['sent_at'] = null;
            }

            $reminder->update($reminderData);

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

    public static function queueTasks(string $chatId): array
    {
        try {
            $tasks = TasksModel::query()
                ->where('status', '1')
                ->where('chat_id', $chatId)
                ->withWhereHas(
                    'reminders',
                    fn ($query) => $query->where('chat_id', $chatId)->whereNull('deleted_at')
                )
                ->orderByDesc('created_at')
                ->orderByDesc('id')
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

    public static function deleteTask(int $taskId, string $chatId): array
    {
        try {
            DB::beginTransaction();

            $task = TasksModel::query()->where('chat_id', $chatId)->find($taskId);

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

    public static function getTasks(string $chatId, array $filters = []): array
    {
        try {
            $tasks = TasksModel::query()
                ->where('chat_id', $chatId)
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
                ->orderByDesc('created_at')
                ->orderByDesc('id')
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

    public static function createTask(array $task_array, array $reminder_array, string $chatId): array
    {
        try {

            DB::beginTransaction();

            $task_array['chat_id'] = $chatId;
            $task_array['status'] = '1';
            $task = TasksModel::create($task_array);

            $reminder_array['task_id'] = $task->id;
            $reminder_array['chat_id'] = $chatId;
            ReminderModel::create($reminder_array);

            DB::commit();

            return [
                'msg' => 'inserted',
                'data' => $task->load('reminders'),
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
