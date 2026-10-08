<?php

namespace Tests\Feature;

use App\Http\Controllers\TelegramWebhookController;
use App\Models\ChatMessage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'crm.telegram.bot_token' => 'test-token',
            'crm.telegram.webhook_secret' => 'hook-secret',
        ]);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    private function update(string $text, int $chatId = 555): array
    {
        return ['update_id' => 1, 'message' => ['message_id' => 1, 'chat' => ['id' => $chatId, 'type' => 'private'], 'text' => $text]];
    }

    private function webhook(array $payload, string $secret = 'hook-secret')
    {
        return $this->postJson('/api/telegram/webhook', $payload, ['X-Telegram-Bot-Api-Secret-Token' => $secret]);
    }

    private function sentTexts(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0]['text'] ?? null)->filter()->values()->all();
    }

    public function test_rejects_requests_without_the_secret(): void
    {
        $this->webhook($this->update('hi'), 'wrong')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_start_with_link_code_links_the_chat_to_the_student(): void
    {
        $user = User::factory()->create();
        $code = $user->issueTelegramLinkCode();

        $this->webhook($this->update("/start {$code}"))->assertOk();

        $user->refresh();
        $this->assertSame('555', $user->telegram_chat_id);
        $this->assertNull($user->telegram_link_code);
        Http::assertSent(fn (Request $r) => str_contains($r['text'], 'Berjaya dipautkan'));
    }

    public function test_link_code_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $code = $user->issueTelegramLinkCode();
        $this->webhook($this->update("/start {$code}"))->assertOk();

        $this->webhook($this->update("/start {$code}", 999))->assertOk();

        $this->assertSame('555', $user->fresh()->telegram_chat_id);
        Http::assertSent(fn (Request $r) => $r['chat_id'] === '999' && str_contains($r['text'], 'tidak sah'));
    }

    public function test_unlinked_chat_is_told_how_to_link(): void
    {
        $this->webhook($this->update('apa task hari ni'))->assertOk();

        Http::assertSent(fn (Request $r) => $r['text'] === TelegramWebhookController::NOT_LINKED);
    }

    public function test_linked_student_asking_about_today_gets_their_tasks(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['telegram_chat_id' => '555'])->save();
        Task::factory()->for($user)->create(['title' => 'Assignment Fizik', 'due_date' => today()]);
        Task::factory()->for($user)->create(['title' => 'Projek akhir', 'due_date' => today()->addDays(20)]);
        Task::factory()->create(['title' => 'Task orang lain', 'due_date' => today()]);

        $this->webhook($this->update('Apa task saya harini?'))->assertOk();

        $reply = collect($this->sentTexts())->last();
        $this->assertStringContainsString('Assignment Fizik', $reply);
        $this->assertStringNotContainsString('Projek akhir', $reply);
        $this->assertStringNotContainsString('Task orang lain', $reply);

        $this->assertSame(2, ChatMessage::where('user_id', $user->id)->count());
    }

    public function test_group_chats_are_ignored(): void
    {
        $payload = $this->update('/today');
        $payload['message']['chat']['type'] = 'group';

        $this->webhook($payload)->assertOk();
        Http::assertNothingSent();
    }
}
