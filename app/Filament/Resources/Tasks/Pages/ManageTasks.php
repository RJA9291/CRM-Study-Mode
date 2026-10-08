<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTasks extends ManageRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Beri task')
                ->using(function (array $data): Task {
                    $tasks = collect((array) $data['user_id'])->map(fn ($userId) => Task::create([
                        ...$data,
                        'user_id' => $userId,
                        'assigned_by' => auth()->id(),
                    ]));

                    return $tasks->first();
                }),
        ];
    }
}
