<x-filament-panels::page>
    <x-filament::section icon="heroicon-o-paper-airplane" heading="Telegram">
        @if ($this->isTelegramLinked())
            <x-filament::badge color="success" style="display:inline-flex">Telegram dipautkan</x-filament::badge>
            <p style="margin-top:.5rem">Hantar <code>/today</code> kepada bot untuk task hari ini, atau tanya apa-apa soalan.</p>
        @else
            <p>Telegram belum dipautkan. Klik <strong>Link Telegram</strong> di atas, kemudian buka pautan yang dipaparkan dan tekan <em>Start</em> dalam Telegram.</p>
        @endif

        @if ($telegramLink)
            <div style="margin-top:.75rem">
                <x-filament::button tag="a" :href="$telegramLink" target="_blank" icon="heroicon-o-arrow-top-right-on-square">
                    Buka bot di Telegram
                </x-filament::button>
                <p style="margin-top:.5rem;font-size:.875rem;opacity:.75">Pautan ini sekali guna sahaja. Jangan kongsi dengan orang lain.</p>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section icon="heroicon-o-chat-bubble-left-right" heading="Chat">
        <div style="display:flex;flex-direction:column;gap:.5rem;max-height:28rem;overflow-y:auto;margin-bottom:1rem" id="chat-log">
            @forelse ($this->messages as $msg)
                @php($mine = $msg->direction === \App\Models\ChatMessage::INBOUND)
                <div style="display:flex;justify-content:{{ $mine ? 'flex-end' : 'flex-start' }}">
                    <div style="max-width:80%;white-space:pre-line;padding:.5rem .75rem;border-radius:.75rem;font-size:.9rem;{{ $mine ? 'background:color-mix(in srgb, var(--primary-500) 18%, transparent)' : 'background:rgba(127,127,127,.12)' }}">{{ $msg->body }}<div style="font-size:.7rem;opacity:.6;margin-top:.25rem">{{ $msg->channel }} · {{ $msg->created_at->format('d/m H:i') }}</div></div>
                </div>
            @empty
                <p style="opacity:.7">Belum ada perbualan. Cuba tanya: <em>"Apa task saya hari ni?"</em></p>
            @endforelse
        </div>

        <form wire:submit="send" style="display:flex;gap:.5rem;align-items:flex-end">
            <x-filament::input.wrapper style="flex:1">
                <x-filament::input type="text" wire:model="message" placeholder="Tulis soalan anda…" maxlength="2000" autocomplete="off" />
            </x-filament::input.wrapper>
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled" wire:target="send">
                Hantar
            </x-filament::button>
        </form>
        <div wire:loading wire:target="send" style="font-size:.85rem;opacity:.7;margin-top:.5rem">Sedang menjawab…</div>
    </x-filament::section>
</x-filament-panels::page>
