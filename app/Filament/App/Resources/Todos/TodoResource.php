<?php

namespace App\Filament\App\Resources\Todos;

use App\Filament\App\Concerns\ScopedToCurrentUser;
use App\Filament\App\Resources\Todos\Pages\ManageTodos;
use App\Models\Todo;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TodoResource extends Resource
{
    use ScopedToCurrentUser;

    protected static ?string $model = Todo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $modelLabel = 'to-do';

    protected static ?string $pluralModelLabel = 'To-do List';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Perkara')->required()->maxLength(255)->columnSpanFull(),
            DatePicker::make('for_date')->label('Untuk tarikh')->native(false)->default(today()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('is_done')->orderByDesc('for_date'))
            ->columns([
                CheckboxColumn::make('is_done')->label('Siap'),
                TextColumn::make('title')->label('Perkara')->searchable()->wrap(),
                TextColumn::make('for_date')->label('Tarikh')->date('d M Y')->sortable()->placeholder('Bila-bila'),
            ])
            ->filters([
                TernaryFilter::make('is_done')->label('Siap'),
                Filter::make('today')->label('Hari ini')->query(fn (Builder $query) => $query->whereDate('for_date', today())),
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
            'index' => ManageTodos::route('/'),
        ];
    }
}
