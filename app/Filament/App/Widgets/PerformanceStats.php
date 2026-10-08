<?php

namespace App\Filament\App\Widgets;

use App\Services\PerformanceReport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PerformanceStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Prestasi task';

    protected function getStats(): array
    {
        $report = app(PerformanceReport::class);
        $s = $report->summary(auth()->id());
        $trend = array_values($report->completionsPerDay(auth()->id(), 14));

        return [
            Stat::make('Task belum siap', $s['open'])
                ->description($s['overdue'] > 0 ? "{$s['overdue']} lewat tarikh" : 'Tiada yang lewat')
                ->color($s['overdue'] > 0 ? 'danger' : 'success'),
            Stat::make('Kadar siap', $s['completion_rate'] === null ? '–' : $s['completion_rate'].'%')
                ->description("{$s['done']} daripada {$s['total']} task")
                ->color('primary'),
            Stat::make('Siap tepat masa', $s['on_time_rate'] === null ? '–' : $s['on_time_rate'].'%')
                ->description('Daripada task bertarikh akhir yang siap')
                ->color(($s['on_time_rate'] ?? 100) >= 80 ? 'success' : 'warning'),
            Stat::make('Siap 7 hari lepas', $s['done_last_7_days'])
                ->chart($trend)
                ->color('info'),
        ];
    }
}
