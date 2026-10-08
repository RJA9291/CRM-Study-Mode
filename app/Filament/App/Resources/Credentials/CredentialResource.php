<?php

namespace App\Filament\App\Resources\Credentials;

use App\Filament\App\Concerns\ScopedToCurrentUser;
use App\Filament\App\Resources\Credentials\Pages\ManageCredentials;
use App\Models\Credential;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CredentialResource extends Resource
{
    use ScopedToCurrentUser;

    public const UNLOCK_SESSION_KEY = 'locker_unlocked_at';

    public const UNLOCK_SECONDS = 600;

    protected static ?string $model = Credential::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $modelLabel = 'akaun';

    protected static ?string $pluralModelLabel = 'ID & Password Locker';

    protected static ?string $slug = 'locker';

    protected static ?int $navigationSort = 3;

    public static function isUnlocked(): bool
    {
        $at = session(self::UNLOCK_SESSION_KEY);

        return is_int($at) && (time() - $at) < self::UNLOCK_SECONDS;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->label('Nama akaun')->placeholder('cth: Email college, Portal pelajar')->required()->maxLength(100),
            Select::make('category')->label('Kategori')->options(Credential::CATEGORIES)->default('web')->required(),
            TextInput::make('url')->label('URL / aplikasi')->url()->maxLength(255)->columnSpanFull(),
            TextInput::make('username')->label('ID / username / email')->maxLength(255),
            TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Biarkan kosong untuk kekalkan password sedia ada.' : null)
                ->maxLength(500),
            Textarea::make('notes')->label('Nota (disulitkan)')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('label')
            ->columns([
                TextColumn::make('label')->label('Akaun')->searchable()->weight('medium')
                    ->description(fn (Credential $record) => Credential::CATEGORIES[$record->category] ?? $record->category),
                TextColumn::make('username')->label('ID / username')->searchable()->copyable(),
                TextColumn::make('password')
                    ->label('Password')
                    ->state('••••••••')
                    ->copyable(fn () => static::isUnlocked())
                    ->copyableState(fn (Credential $record) => static::isUnlocked() ? $record->password : null)
                    ->copyMessage('Password disalin')
                    ->tooltip(fn () => static::isUnlocked() ? 'Klik untuk salin' : 'Buka kunci locker untuk salin'),
                TextColumn::make('url')->label('URL')->url(fn (Credential $record) => $record->url, shouldOpenInNewTab: true)
                    ->limit(40)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Kategori')->options(Credential::CATEGORIES),
            ])
            ->recordActions([
                Action::make('reveal')
                    ->label('Lihat')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn () => static::isUnlocked())
                    ->modalHeading(fn (Credential $record) => $record->label)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->fillForm(fn (Credential $record) => [
                        'username' => $record->username,
                        'password' => $record->password,
                        'notes' => $record->notes,
                    ])
                    ->schema([
                        TextInput::make('username')->label('ID / username')->readOnly()->copyable(),
                        TextInput::make('password')->label('Password')->readOnly()->copyable(),
                        Textarea::make('notes')->label('Nota')->readOnly()->rows(3),
                    ]),
                EditAction::make()
                    ->visible(fn () => static::isUnlocked())
                    ->mutateRecordDataUsing(fn (array $data, Credential $record) => [...$data, 'notes' => $record->notes]),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCredentials::route('/'),
        ];
    }
}
