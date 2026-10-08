<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'title', 'is_done', 'for_date'])]
class Todo extends Model
{
    protected $attributes = [
        'is_done' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'for_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
