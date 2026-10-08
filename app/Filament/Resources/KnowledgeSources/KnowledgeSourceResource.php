<?php

namespace App\Filament\Resources\KnowledgeSources;

use App\Filament\Resources\KnowledgeSources\Pages\ManageKnowledgeSources;
use App\Jobs\SyncKnowledgeSource;
use App\Models\KnowledgeSource;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class KnowledgeSourceResource extends Resource
{
    protected static ?string $model = KnowledgeSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'folder knowledge';

    protected static ?string $pluralModelLabel = 'Knowledge (Google Drive)';

    protected static ?string $slug = 'knowledge';

    protected static ?int $navigationSort = 3;

    public static function serviceAccountEmail(): ?string
    {
        $path = (string) config('crm.google.service_account_json');
        $path = is_file($path) ? $path : base_path($path);

        if (! is_file($path)) {
            return null;
        }

        return json_decode((string) file_get_contents($path), true)['client_email'] ?? null;
    }

    public static function form(Schema $schema): Schema
    {
        $email = static::serviceAccountEmail();

        return $schema->components([
            TextInput::make('name')->label('Nama')->placeholder('cth: Nota Semester 3')->required()->maxLength(150),
            TextInput::make('drive_url')
                ->label('Link folder Google Drive')
                ->placeholder('https://drive.google.com/drive/folders/…')
                ->required()
                ->maxLength(500)
                ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                    if (! KnowledgeSource::extractFolderId($value)) {
                        $fail('Link folder Google Drive tidak sah.');
                    }
                })
                ->helperText($email
                    ? "Kongsi (Share → Viewer) folder ini kepada {$email} supaya sistem boleh membacanya."
                    : 'Kunci service account Google belum dipasang di server — sync tidak akan berjaya sehingga ia dipasang.')
                ->columnSpanFull(),
            Toggle::make('is_active')->label('Aktif untuk chatbot')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()
                    ->description(fn (KnowledgeSource $record) => $record->drive_folder_id),
                ToggleColumn::make('is_active')->label('Aktif'),
                TextColumn::make('documents_count')->counts('documents')->label('Dokumen'),
                TextColumn::make('sync_status')->label('Status sync')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'ok' => 'success',
                        'syncing' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (KnowledgeSource $record) => $record->sync_error),
                TextColumn::make('last_synced_at')->label('Sync terakhir')->since()->placeholder('Belum pernah'),
            ])
            ->recordActions([
                Action::make('sync')
                    ->label('Sync sekarang')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(function (KnowledgeSource $record) {
                        SyncKnowledgeSource::dispatch($record);
                        Notification::make()->title('Sync dimulakan')->body('Dokumen akan dikemas kini di latar belakang.')->success()->send();
                    }),
                Action::make('open')
                    ->label('Buka')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (KnowledgeSource $record) => $record->drive_url, shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->poll('10s');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageKnowledgeSources::route('/'),
        ];
    }
}
