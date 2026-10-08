<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'label', 'category', 'url', 'username', 'password', 'notes'])]
#[Hidden(['password', 'notes'])]
class Credential extends Model
{
    public const CATEGORIES = [
        'email' => 'Email',
        'portal' => 'Portal college',
        'lms' => 'LMS / e-learning',
        'app' => 'Aplikasi',
        'web' => 'Laman web lain',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'notes' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
