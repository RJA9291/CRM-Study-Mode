<?php

namespace App\Http\Controllers;

use App\Jobs\RespondToChatMessage;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Assistant\StudentAssistant;
use App\Services\Messaging\TelegramChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    public const NOT_LINKED = 'Akaun Telegram ini belum dipautkan. Log masuk ke dashboard CRM → Chat Assistant → "Link Telegram".';

    public const NOT_APPROVED = 'Akaun anda belum diluluskan oleh admin.';

    public function __invoke(Request $request, TelegramChannel $telegram): JsonResponse
    {
        $secret = (string) config('crm.telegram.webhook_secret');
        abort_if($secret === '' || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $chatId = $request->input('message.chat.id');
        $text = trim((string) $request->input('message.text', ''));

        if ($chatId === null || $text === '' || $request->input('message.chat.type') !== 'private') {
            return response()->json(['ok' => true]);
        }

        $chatId = (string) $chatId;

        if (Str::startsWith($text, '/start ')) {
            $this->link($telegram, $chatId, trim(Str::after($text, '/start ')));

            return response()->json(['ok' => true]);
        }

        $user = User::where('telegram_chat_id', $chatId)->first();

        if (! $user) {
            $telegram->send($chatId, self::NOT_LINKED);

            return response()->json(['ok' => true]);
        }

        if (! $user->isApproved()) {
            $telegram->send($chatId, self::NOT_APPROVED);

            return response()->json(['ok' => true]);
        }

        ChatMessage::create([
            'user_id' => $user->id,
            'channel' => 'telegram',
            'direction' => ChatMessage::INBOUND,
            'body' => Str::limit($text, 4000, ''),
        ]);

        RespondToChatMessage::dispatch($user, 'telegram', $chatId, Str::limit($text, 4000, ''));

        return response()->json(['ok' => true]);
    }

    private function link(TelegramChannel $telegram, string $chatId, string $code): void
    {
        $user = $code !== '' ? User::where('telegram_link_code', $code)->first() : null;

        if (! $user || ! $user->isApproved()) {
            $telegram->send($chatId, 'Kod pautan tidak sah atau telah tamat. Jana kod baru dari dashboard.');

            return;
        }

        User::where('telegram_chat_id', $chatId)->whereKeyNot($user->id)->update(['telegram_chat_id' => null]);

        $user->forceFill(['telegram_chat_id' => $chatId, 'telegram_link_code' => null])->save();

        $telegram->send($chatId, "Berjaya dipautkan ke akaun {$user->name}. ✅\n\n".StudentAssistant::HELP);
    }
}
