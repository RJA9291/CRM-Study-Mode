<?php

namespace App\Filament\App\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Every student-panel record belongs to the signed-in user; this is the only access boundary.
 */
trait ScopedToCurrentUser
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
