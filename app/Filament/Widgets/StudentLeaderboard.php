<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class StudentLeaderboard extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $today = today()->toDateString();

        return $table
            ->heading('Prestasi pelajar')
            ->query(fn () => User::query()
                ->where('role', UserRole::Student->value)
                ->where('status', UserStatus::Approved->value)
                ->withCount([
                    'tasks',
                    'tasks as done_count' => fn (Builder $q) => $q->where('status', TaskStatus::Done->value),
                    'tasks as overdue_count' => fn (Builder $q) => $q->where('status', '!=', TaskStatus::Done->value)
                        ->whereNotNull('due_date')->whereDate('due_date', '<', $today),
                ]))
            ->defaultSort('overdue_count', 'desc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('name')->label('Pelajar')->searchable(),
                TextColumn::make('tasks_count')->label('Jumlah task')->sortable(),
                TextColumn::make('done_count')->label('Siap')->sortable(),
                TextColumn::make('overdue_count')->label('Lewat')->sortable()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : null),
                TextColumn::make('rate')->label('Kadar siap')
                    ->state(fn (User $record) => $record->tasks_count ? round($record->done_count / $record->tasks_count * 100).'%' : '–'),
            ]);
    }
}
