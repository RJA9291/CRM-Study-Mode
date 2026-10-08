<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data) => UserResource::persist(new User, $data)),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua'),
            'pending' => Tab::make('Menunggu kelulusan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', UserStatus::Pending->value))
                ->badge(fn () => static::getModel()::where('status', UserStatus::Pending->value)->count() ?: null),
            'approved' => Tab::make('Diluluskan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', UserStatus::Approved->value)),
        ];
    }
}
