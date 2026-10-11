<?php

namespace App\Filament\Resources\ChatMessages\Pages;

use App\Filament\Resources\ChatMessages\ChatMessageResource;
use App\Services\Messaging\TelegramChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;

class ListChatMessages extends ListRecords
{
    protected static string $resource = ChatMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('telegramStatus')
                ->label('Status Telegram')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(fn (TelegramChannel $telegram) => $this->reportTelegram($telegram)),
            Action::make('registerTelegramWebhook')
                ->label('Daftar webhook Telegram')
                ->icon(Heroicon::OutlinedLink)
                ->requiresConfirmation()
                ->modalDescription('Telegram akan menghantar mesej bot ke '.route('telegram.webhook').'.')
                ->action(function (TelegramChannel $telegram) {
                    $secret = (string) config('crm.telegram.webhook_secret');
                    if ($secret === '') {
                        Notification::make()->title('TELEGRAM_WEBHOOK_SECRET belum diisi dalam .env')->danger()->send();

                        return;
                    }

                    try {
                        $telegram->setWebhook(route('telegram.webhook'), $secret);
                    } catch (Throwable $e) {
                        Notification::make()->title('Gagal daftar webhook')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }

                    $this->reportTelegram($telegram);
                }),
        ];
    }

    private function reportTelegram(TelegramChannel $telegram): void
    {
        try {
            $bot = $telegram->getMe();
            $info = $telegram->getWebhookInfo();
        } catch (Throwable $e) {
            Notification::make()->title('Telegram tidak dapat dihubungi')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        $registered = ($info['url'] ?? '') === route('telegram.webhook');
        $lines = [
            'Bot: @'.e($bot['username'] ?? '?'),
            'Webhook: '.e(($info['url'] ?? '') ?: 'belum didaftar'),
            'Mesej menunggu: '.(int) ($info['pending_update_count'] ?? 0),
        ];
        if (! empty($info['last_error_message'])) {
            $lines[] = 'Ralat terakhir: '.e($info['last_error_message']);
        }

        Notification::make()
            ->title($registered ? 'Telegram bersambung' : 'Webhook belum didaftar')
            ->body(new HtmlString(implode('<br>', $lines)))
            ->color($registered ? 'success' : 'warning')
            ->persistent()
            ->send();
    }
}
