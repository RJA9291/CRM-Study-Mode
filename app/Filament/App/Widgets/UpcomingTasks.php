<?php

namespace App\Filament\App\Widgets;

use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingTasks extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Task akan datang & lewat')
            ->query(fn () => Task::query()
                ->where('user_id', auth()->id())
                ->open()
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', today()->addDays(7))
                ->orderBy('due_date'))
            ->paginated([5])
            ->emptyStateHeading('Tiada task dalam 7 hari ini')
            ->columns([
                TextColumn::make('title')->label('Tajuk')->wrap(),
                TextColumn::make('subject')->label('Subjek'),
                TextColumn::make('due_date')->label('Tarikh akhir')->date('d M Y')
                    ->color(fn (Task $record) => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label('Siap')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->action(fn (Task $record) => $record->update(['status' => TaskStatus::Done])),
            ]);
    }
}
