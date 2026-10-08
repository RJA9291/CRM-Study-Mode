<?php

namespace App\Filament\App\Pages;

use App\Models\ChatMessage;
use App\Services\Assistant\StudentAssistant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatAssistant extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Chat Assistant';

    protected static ?string $title = 'Chat Assistant';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.app.pages.chat-assistant';

    public string $message = '';

    public ?string $telegramLink = null;

    public function getSubheading(): ?string
    {
        return 'Tanya task hari ini atau soalan berdasarkan folder knowledge college — di sini atau melalui Telegram.';
    }

    public function send(StudentAssistant $assistant): void
    {
        $text = Str::limit(trim($this->message), 2000, '');
        if ($text === '') {
            return;
        }

        $user = auth()->user();
        ChatMessage::create(['user_id' => $user->id, 'channel' => 'web', 'direction' => ChatMessage::INBOUND, 'body' => $text]);

        $reply = $assistant->reply($user, $text);
        ChatMessage::create(['user_id' => $user->id, 'channel' => 'web', 'direction' => ChatMessage::OUTBOUND, 'body' => $reply]);

        $this->message = '';
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    public function getMessagesProperty(): Collection
    {
        return auth()->user()->chatMessages()->latest('id')->limit(40)->get()->reverse()->values();
    }

    public function isTelegramLinked(): bool
    {
        return filled(auth()->user()->telegram_chat_id);
    }

    public function botUsername(): ?string
    {
        return config('crm.telegram.bot_username');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('linkTelegram')
                ->label(fn () => $this->isTelegramLinked() ? 'Pautkan semula Telegram' : 'Link Telegram')
                ->icon(Heroicon::OutlinedLink)
                ->disabled(fn () => blank($this->botUsername()))
                ->tooltip(fn () => blank($this->botUsername()) ? 'Admin belum set TELEGRAM_BOT_USERNAME' : null)
                ->action(function () {
                    $code = auth()->user()->issueTelegramLinkCode();
                    $this->telegramLink = 'https://t.me/'.$this->botUsername().'?start='.$code;
                }),
            Action::make('unlinkTelegram')
                ->label('Putuskan Telegram')
                ->color('danger')
                ->outlined()
                ->requiresConfirmation()
                ->visible(fn () => $this->isTelegramLinked())
                ->action(function () {
                    auth()->user()->forceFill(['telegram_chat_id' => null, 'telegram_link_code' => null])->save();
                    $this->telegramLink = null;
                    Notification::make()->title('Telegram diputuskan')->success()->send();
                }),
        ];
    }
}
