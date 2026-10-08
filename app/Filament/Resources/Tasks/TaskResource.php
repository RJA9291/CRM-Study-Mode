<?php

namespace App\Filament\Resources\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Tasks\Pages\ManageTasks;
use App\Models\Task;
use App\Models\User;
use BackedEnum;
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
use Filament\Tables\Table;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'task';

    protected static ?string $pluralModelLabel = 'Task Pelajar';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Pelajar')
                ->options(fn () => User::where('role', UserRole::Student->value)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->multiple(fn (string $operation) => $operation === 'create')
                ->helperText(fn (string $operation) => $operation === 'create' ? 'Pilih lebih dari seorang untuk beri task yang sama.' : null),
            TextInput::make('title')->label('Tajuk')->required()->maxLength(255),
            TextInput::make('subject')->label('Subjek')->maxLength(100),
            DatePicker::make('due_date')->label('Tarikh akhir')->native(false),
            Select::make('priority')->label('Keutamaan')->options(TaskPriority::class)->default(TaskPriority::Medium)->required(),
            Select::make('status')->options(TaskStatus::class)->default(TaskStatus::Todo)->required(),
            Textarea::make('description')->label('Keterangan')->rows(4)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Pelajar')->searchable()->sortable(),
                TextColumn::make('title')->label('Tajuk')->searchable()->wrap(),
                TextColumn::make('subject')->label('Subjek')->toggleable(),
                TextColumn::make('due_date')->label('Tarikh akhir')->date('d M Y')->sortable()
                    ->color(fn (Task $record) => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('priority')->label('Keutamaan')->badge(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('assigner.name')->label('Diberi oleh')->placeholder('Pelajar sendiri')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('user_id')->label('Pelajar')->relationship('user', 'name')->searchable(),
                SelectFilter::make('status')->options(TaskStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
