<?php

namespace App\Filament\App\Resources\Todos\Pages;

use App\Filament\App\Resources\Todos\TodoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTodos extends ManageRecords
{
    protected static string $resource = TodoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data) => [...$data, 'user_id' => auth()->id()]),
        ];
    }
}
