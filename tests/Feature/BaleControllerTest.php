<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_can_be_registered_using_a_get_route(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $webhookUrl = 'https://aiva.startabad.com/aiva/api/v1/bale/webhook';

        $this->getJson('/api/v1/bale/set-webhook?'.http_build_query([
            'url' => $webhookUrl,
        ]))->assertOk()->assertJson(['ok' => true]);

        Http::assertSent(fn ($request) =>
            str_ends_with($request->url(), '/setWebhook')
            && $request->method() === 'POST'
            && $request['url'] === $webhookUrl
        );
    }

    public function test_webhook_registration_requires_an_https_url(): void
    {
        Http::fake();

        $this->getJson('/api/v1/bale/set-webhook?url=http://example.com/webhook')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');

        Http::assertNothingSent();
    }

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
            && str_contains($request['text'], '⏰ یادآوری تسک')
            && str_contains($request['text'], '📝 عنوان: Pay invoice')
            && str_contains($request['text'], '🏷 نوع: reminder')
            && str_contains($request['text'], '💬 توضیحات: Invoice number 42')
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
                'message' => [
                    'chat' => ['id' => 123456],
                ],
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

        Http::assertSent(fn ($request) =>
            str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === '123456'
        );
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
        Http::assertSent(fn ($request) =>
            str_contains($request['text'], '📌 تسک ۱')
            && str_contains($request['text'], '📝 عنوان: First task')
            && str_contains($request['text'], '🗓 زمان یادآوری:')
            && str_contains($request['text'], '۱۵:۳۰')
        );
        Http::assertSent(fn ($request) =>
            str_contains($request['text'], '📌 تسک ۲')
            && str_contains($request['text'], '📝 عنوان: Second task')
            && str_contains($request['text'], '🗓 زمان یادآوری:')
            && str_contains($request['text'], '۱۵:۳۰')
        );
    }

    public function test_report_excludes_completed_tasks(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        foreach ([
            ['title' => 'Pending task', 'status' => '1'],
            ['title' => 'Completed task', 'status' => '2'],
        ] as $item) {
            $taskId = DB::table('tasks')->insertGetId([
                'title' => $item['title'],
                'chat_id' => '123456',
                'type' => 'daily',
                'comments' => 'Report item',
                'status' => $item['status'],
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
            'sent' => 1,
            'failed' => 0,
        ]);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) =>
            str_contains($request['text'], 'Pending task')
            && ! str_contains($request['text'], 'Completed task')
        );
    }

    public function test_report_includes_incomplete_tasks_from_other_dates(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $taskId = DB::table('tasks')->insertGetId([
            'title' => 'Future pending task',
            'chat_id' => '123456',
            'type' => 'daily',
            'comments' => 'Future reminder',
            'status' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('reminder')->insert([
            'chat_id' => '123456',
            'task_id' => $taskId,
            'remind_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/bale/webhook', [
            'message' => [
                'from' => ['id' => 123456],
                'text' => '/report',
            ],
        ])->assertOk()->assertJson([
            'ok' => true,
            'sent' => 1,
            'failed' => 0,
        ]);

        Http::assertSent(fn ($request) =>
            str_contains($request['text'], 'Future pending task')
        );
    }

    public function test_report_formats_reminder_in_persian_date_and_tehran_time(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $taskId = DB::table('tasks')->insertGetId([
            'title' => 'Timezone task',
            'chat_id' => '123456',
            'type' => 'test',
            'comments' => 'Timezone check',
            'status' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('reminder')->insert([
            'chat_id' => '123456',
            'task_id' => $taskId,
            'remind_at' => '2026-10-18 23:45:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/bale/webhook', [
            'message' => [
                'from' => ['id' => 123456],
                'text' => '/report',
            ],
        ])->assertOk();

        Http::assertSent(fn ($request) =>
            str_contains($request['text'], '۱۴۰۵/۰۷/۲۷ - ۰۳:۱۵')
        );
    }
}
