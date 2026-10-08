<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Assistant\StudentAssistant;
use App\Services\Messaging\MessagingChannel;
use App\Services\Messaging\TelegramChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RespondToChatMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public User $user,
        public string $channel,
        public string $recipient,
        public string $text,
    ) {}

    public function handle(StudentAssistant $assistant): void
    {
        $reply = $assistant->reply($this->user, $this->text);

        $this->messagingChannel()->send($this->recipient, $reply);

        ChatMessage::create([
            'user_id' => $this->user->id,
            'channel' => $this->channel,
            'direction' => ChatMessage::OUTBOUND,
            'body' => $reply,
        ]);
    }

    private function messagingChannel(): MessagingChannel
    {
        return match ($this->channel) {
            'telegram' => app(TelegramChannel::class),
        };
    }
}
