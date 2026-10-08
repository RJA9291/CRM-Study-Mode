<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Services\PerformanceReport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'Pelajar & Pengguna';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $pending = User::where('status', UserStatus::Pending->value)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Menunggu kelulusan';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('student_id')->label('No. matrik')->maxLength(50),
            TextInput::make('phone')->label('Telefon')->tel()->maxLength(30),
            TextInput::make('programme')->label('Program')->maxLength(150),
            TextInput::make('semester')->numeric()->minValue(1)->maxValue(20),
            Select::make('role')->label('Peranan')->options(UserRole::class)->required()
                ->disabled(fn (?User $record) => $record?->is(auth()->user())),
            Select::make('status')->options(UserStatus::class)->required()
                ->disabled(fn (?User $record) => $record?->is(auth()->user())),
            TextInput::make('password')
                ->label('Password baharu')
                ->password()
                ->revealable()
                ->minLength(8)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state)),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable()
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('student_id')->label('No. matrik')->searchable()->toggleable(),
                TextColumn::make('programme')->label('Program')->toggleable(),
                TextColumn::make('role')->label('Peranan')->badge(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('tasks_count')->counts('tasks')->label('Task')->sortable(),
                TextColumn::make('completion')
                    ->label('Kadar siap')
                    ->state(fn (User $record) => ($r = app(PerformanceReport::class)->summary($record->id)['completion_rate']) === null ? '–' : $r.'%'),
                TextColumn::make('telegram_chat_id')->label('Telegram')
                    ->formatStateUsing(fn ($state) => $state ? 'Dipautkan' : null)->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Daftar')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(UserStatus::class),
                SelectFilter::make('role')->label('Peranan')->options(UserRole::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Lulus')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (User $record) => $record->status !== UserStatus::Approved)
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->approve();
                        Notification::make()->title("{$record->name} diluluskan")->success()->send();
                    }),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (User $record) => $record->status === UserStatus::Pending)
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->reject()),
                EditAction::make()->using(fn (User $record, array $data) => static::persist($record, $data)),
                DeleteAction::make()->hidden(fn (User $record) => $record->is(auth()->user())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label('Luluskan dipilih')
                        ->icon(Heroicon::OutlinedCheck)
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->approve())
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * Role and status are deliberately not mass-assignable, so admin edits are force-filled here.
     */
    public static function persist(User $user, array $data): User
    {
        $user->forceFill($data);

        if ($user->isDirty('status')) {
            $user->approved_at = $user->status === UserStatus::Approved ? now() : null;
        }

        $user->save();

        return $user;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
