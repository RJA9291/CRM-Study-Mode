<?php

namespace App\Filament\Resources\ChatMessages;

use App\Filament\Resources\ChatMessages\Pages\ListChatMessages;
use App\Models\ChatMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChatMessageResource extends Resource
{
    protected static ?string $model = ChatMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $modelLabel = 'mesej';

    protected static ?string $pluralModelLabel = 'Log Chat';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Masa')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('user.name')->label('Pelajar')->searchable(),
                TextColumn::make('channel')->label('Saluran')->badge(),
                TextColumn::make('direction')->label('Arah')
                    ->formatStateUsing(fn (string $state) => $state === ChatMessage::INBOUND ? 'Pelajar → Bot' : 'Bot → Pelajar'),
                TextColumn::make('body')->label('Mesej')->limit(120)->wrap()->searchable(),
            ])
            ->filters([
                SelectFilter::make('user_id')->label('Pelajar')->relationship('user', 'name')->searchable(),
                SelectFilter::make('channel')->label('Saluran')->options(['telegram' => 'Telegram', 'web' => 'Web', 'whatsapp' => 'WhatsApp']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChatMessages::route('/'),
        ];
    }
}
