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

        $this
            ->withToken('test-cron-token')
            ->postJson('/api/v1/bale/tasks/dispatch-due')
            ->assertOk()
            ->assertJson(['sent' => 0]);

        Http::assertSentCount(1);
    }
}
