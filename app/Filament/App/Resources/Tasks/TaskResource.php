<?php

namespace App\Filament\App\Resources\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\App\Concerns\ScopedToCurrentUser;
use App\Filament\App\Resources\Tasks\Pages\ManageTasks;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaskResource extends Resource
{
    use ScopedToCurrentUser;

    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'task';

    protected static ?string $pluralModelLabel = 'Task';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $open = static::getEloquentQuery()->open()->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function form(Schema $schema): Schema
    {
        $assigned = fn (?Task $record) => (bool) $record?->assigned_by;

        return $schema->components([
            TextInput::make('title')->label('Tajuk')->required()->maxLength(255)->disabled($assigned)->columnSpanFull(),
            TextInput::make('subject')->label('Subjek')->maxLength(100)->disabled($assigned),
            DatePicker::make('due_date')->label('Tarikh akhir')->native(false)->disabled($assigned),
            Select::make('priority')->label('Keutamaan')->options(TaskPriority::class)->default(TaskPriority::Medium)->required()->disabled($assigned),
            Select::make('status')->options(TaskStatus::class)->default(TaskStatus::Todo)->required(),
            Textarea::make('description')->label('Keterangan')->rows(4)->disabled($assigned)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('title')->label('Tajuk')->searchable()->wrap()
                    ->description(fn (Task $record) => $record->assigned_by ? 'Diberi oleh admin' : null),
                TextColumn::make('subject')->label('Subjek')->searchable()->toggleable(),
                TextColumn::make('due_date')->label('Tarikh akhir')->date('d M Y')->sortable()
                    ->color(fn (Task $record) => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('priority')->label('Keutamaan')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('completed_at')->label('Siap pada')->dateTime('d M Y, H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(TaskStatus::class),
                SelectFilter::make('priority')->label('Keutamaan')->options(TaskPriority::class),
                TernaryFilter::make('overdue')
                    ->label('Lewat')
                    ->queries(
                        true: fn (Builder $query) => $query->open()->whereDate('due_date', '<', today()),
                        false: fn (Builder $query) => $query->where(fn ($q) => $q->where('status', TaskStatus::Done->value)->orWhereNull('due_date')->orWhereDate('due_date', '>=', today())),
                    ),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Siap')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Task $record) => $record->status !== TaskStatus::Done)
                    ->action(fn (Task $record) => $record->update(['status' => TaskStatus::Done])),
                EditAction::make(),
                DeleteAction::make()->visible(fn (Task $record) => ! $record->assigned_by),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTasks::route('/'),
        ];
    }
}
