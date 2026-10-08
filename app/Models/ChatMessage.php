<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'channel', 'direction', 'body'])]
class ChatMessage extends Model
{
    public const INBOUND = 'in';

    public const OUTBOUND = 'out';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
