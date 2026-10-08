<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TelegramChannel implements MessagingChannel
{
    private const MAX_LENGTH = 4096;

    public function __construct(private readonly ?string $token) {}

    public function name(): string
    {
        return 'telegram';
    }

    public function send(string $recipient, string $text): void
    {
        foreach (mb_str_split($text, self::MAX_LENGTH) as $part) {
            $response = $this->request()->post('sendMessage', [
                'chat_id' => $recipient,
                'text' => $part,
                'disable_web_page_preview' => true,
            ]);

            if ($response->failed()) {
                Log::warning('Telegram sendMessage failed', ['status' => $response->status(), 'body' => $response->json('description')]);
            }
        }
    }

    public function setWebhook(string $url, string $secret): array
    {
        return $this->request()->post('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
            'drop_pending_updates' => true,
        ])->throw()->json();
    }

    public function getMe(): array
    {
        return $this->request()->get('getMe')->throw()->json('result', []);
    }

    private function request(): PendingRequest
    {
        if (blank($this->token)) {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not configured.');
        }

        return Http::baseUrl("https://api.telegram.org/bot{$this->token}/")->timeout(15)->asJson();
    }
}
