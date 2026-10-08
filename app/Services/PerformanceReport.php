<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PerformanceReport
{
    /**
     * @param  int|null  $userId  null = all students
     * @return array{total: int, done: int, open: int, overdue: int, completion_rate: ?float, on_time_rate: ?float, done_last_7_days: int}
     */
    public function summary(?int $userId = null): array
    {
        $query = fn (): Builder => Task::query()->when($userId, fn ($q) => $q->where('user_id', $userId));

        $total = $query()->count();
        $done = $query()->where('status', TaskStatus::Done->value)->count();
        $overdue = $query()->open()->whereNotNull('due_date')->whereDate('due_date', '<', today())->count();

        $doneWithDue = $query()->where('status', TaskStatus::Done->value)->whereNotNull('due_date')->whereNotNull('completed_at')
            ->get(['due_date', 'completed_at', 'status']);
        $onTime = $doneWithDue->filter(fn (Task $t) => $t->wasCompletedOnTime())->count();

        return [
            'total' => $total,
            'done' => $done,
            'open' => $total - $done,
            'overdue' => $overdue,
            'completion_rate' => $total > 0 ? round($done / $total * 100, 1) : null,
            'on_time_rate' => $doneWithDue->isNotEmpty() ? round($onTime / $doneWithDue->count() * 100, 1) : null,
            'done_last_7_days' => $query()->where('completed_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /**
     * Completed tasks per day for the last $days days, oldest first.
     *
     * @return array<string, int> keyed by Y-m-d
     */
    public function completionsPerDay(?int $userId = null, int $days = 30): array
    {
        $start = Carbon::today()->subDays($days - 1);

        $counts = Task::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->where('completed_at', '>=', $start)
            ->pluck('completed_at')
            ->countBy(fn ($at) => Carbon::parse($at)->toDateString());

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $series[$day] = (int) ($counts[$day] ?? 0);
        }

        return $series;
    }
}
