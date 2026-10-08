<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'assigned_by', 'title', 'description', 'subject', 'due_date', 'priority', 'status', 'completed_at',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'todo',
        'priority' => 'medium',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            if ($task->isDirty('status')) {
                $task->completed_at = $task->status === TaskStatus::Done ? ($task->completed_at ?? now()) : null;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', TaskStatus::Done->value);
    }

    public function isOverdue(): bool
    {
        return $this->status !== TaskStatus::Done && (bool) $this->due_date?->isBefore(today());
    }

    public function wasCompletedOnTime(): ?bool
    {
        if ($this->status !== TaskStatus::Done || ! $this->due_date || ! $this->completed_at) {
            return null;
        }

        return $this->completed_at->copy()->startOfDay()->lte($this->due_date);
    }
}
