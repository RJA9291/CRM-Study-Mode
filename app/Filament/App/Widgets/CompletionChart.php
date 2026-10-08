<?php

namespace App\Filament\App\Widgets;

use App\Services\PerformanceReport;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class CompletionChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Task siap — 30 hari';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $series = app(PerformanceReport::class)->completionsPerDay(auth()->id(), 30);

        return [
            'datasets' => [
                ['label' => 'Task siap', 'data' => array_values($series)],
            ],
            'labels' => array_map(fn ($d) => Carbon::parse($d)->format('d/m'), array_keys($series)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['ticks' => ['precision' => 0], 'beginAtZero' => true]],
        ];
    }
}
