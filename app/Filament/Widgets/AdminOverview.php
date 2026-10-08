<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\PerformanceReport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $pending = User::where('status', UserStatus::Pending->value)->count();
        $students = User::where('role', UserRole::Student->value)->where('status', UserStatus::Approved->value)->count();
        $s = app(PerformanceReport::class)->summary();

        return [
            Stat::make('Menunggu kelulusan', $pending)
                ->color($pending > 0 ? 'warning' : 'success')
                ->url(route('filament.admin.resources.users.index', ['tab' => 'pending'])),
            Stat::make('Pelajar aktif', $students),
            Stat::make('Kadar siap task', $s['completion_rate'] === null ? '–' : $s['completion_rate'].'%')
                ->description("{$s['open']} belum siap · {$s['overdue']} lewat"),
            Stat::make('Dokumen knowledge', KnowledgeDocument::count()),
        ];
    }
}
