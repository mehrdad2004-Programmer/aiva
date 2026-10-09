<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_command_stores_sender_and_replies_to_them(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $response = $this->postJson('/api/v1/bale/webhook', [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'from' => [
                    'id' => 123456,
                    'is_bot' => false,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                ],
                'chat' => ['id' => 123456, 'type' => 'private'],
                'date' => now()->timestamp,
                'text' => '/start',
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'chat_id' => '123456',
            'fullname' => 'Test User',
            'deleted_at' => null,
        ]);

        Http::assertSent(fn ($request) => $request['chat_id'] === '123456');
    }

    public function test_due_tasks_are_sent_once_and_marked_as_sent(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $taskId = DB::table('tasks')->insertGetId([
            'title' => 'Pay invoice',
            'chat_id' => '123456',
            'type' => 'reminder',
            'comments' => 'Invoice number 42',
            'status' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reminderId = DB::table('reminder')->insertGetId([
            'chat_id' => '123456',
            'task_id' => $taskId,
            'remind_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withToken('test-cron-token')
            ->postJson('/api/v1/bale/tasks/dispatch-due');

        $response->assertOk()->assertJson([
            'sent' => 1,
            'failed' => 0,
        ]);

        $this->assertNotNull(
            DB::table('reminder')->where('id', $reminderId)->value('sent_at')
        );

        Http::assertSentCount(1);

        Http::assertSent(fn ($request) =>
            data_get($request->data(), 'reply_markup.inline_keyboard.0.0.text') === '✅ انجام شد'
            && data_get($request->data(), 'reply_markup.inline_keyboard.0.0.callback_data') === 'task_done:'.$taskId
        );

        $this
            ->withToken('test-cron-token')
            ->postJson('/api/v1/bale/tasks/dispatch-due')
            ->assertOk()
            ->assertJson(['sent' => 0]);

        Http::assertSentCount(1);
    }

    public function test_done_callback_marks_own_task_as_completed(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $taskId = DB::table('tasks')->insertGetId([
            'title' => 'Complete task',
            'chat_id' => '123456',
            'type' => 'reminder',
            'comments' => 'Complete from inline button',
            'status' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/bale/webhook', [
            'callback_query' => [
                'id' => 'callback-1',
                'from' => ['id' => 123456],
                'data' => 'task_done:'.$taskId,
            ],
        ])->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'chat_id' => '123456',
            'status' => '2',
        ]);

        Http::assertSent(fn ($request) => str_contains(
            $request->url(),
            'answerCallbackQuery'
        ));
    }

    public function test_report_sends_each_task_of_today_in_a_separate_message(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        foreach (['First task', 'Second task'] as $title) {
            $taskId = DB::table('tasks')->insertGetId([
                'title' => $title,
                'chat_id' => '123456',
                'type' => 'daily',
                'comments' => 'Report item',
                'status' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('reminder')->insert([
                'chat_id' => '123456',
                'task_id' => $taskId,
                'remind_at' => now()->setTime(12, 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->postJson('/api/v1/bale/webhook', [
            'message' => [
                'from' => ['id' => 123456],
                'text' => '/report',
            ],
        ])->assertOk()->assertJson([
            'ok' => true,
            'sent' => 2,
            'failed' => 0,
        ]);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['text'] === "تسک 1: First task\nنوع: daily\nوضعیت: در صف\nزمان یادآوری: 12:00\nReport item");
        Http::assertSent(fn ($request) => $request['text'] === "تسک 2: Second task\nنوع: daily\nوضعیت: در صف\nزمان یادآوری: 12:00\nReport item");
    }
}
