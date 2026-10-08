<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Filament\App\Resources\Tasks\Pages\ManageTasks as StudentTasks;
use App\Filament\Resources\Tasks\Pages\ManageTasks as AdminTasks;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Task;
use App\Models\User;
use App\Services\PerformanceReport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_one_task_to_several_students(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [$a, $b] = User::factory()->count(2)->create();

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(AdminTasks::class)
            ->callAction('create', [
                'user_id' => [$a->id, $b->id],
                'title' => 'Hantar laporan makmal',
                'priority' => 'high',
                'status' => 'todo',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $a->tasks()->where('title', 'Hantar laporan makmal')->where('assigned_by', $admin->id)->count());
        $this->assertSame(1, $b->tasks()->where('title', 'Hantar laporan makmal')->count());
    }

    public function test_students_only_see_their_own_tasks_and_cannot_delete_assigned_ones(): void
    {
        $student = User::factory()->create();
        $mine = Task::factory()->for($student)->create();
        $assigned = Task::factory()->for($student)->create(['assigned_by' => User::factory()->superAdmin()->create()->id]);
        $other = Task::factory()->create();

        $this->actingAs($student);
        Filament::setCurrentPanel('app');

        Livewire::test(StudentTasks::class)
            ->assertCanSeeTableRecords([$mine, $assigned])
            ->assertCanNotSeeTableRecords([$other])
            ->assertTableActionVisible('delete', $mine)
            ->assertTableActionHidden('delete', $assigned)
            ->callTableAction('complete', $assigned);

        $this->assertSame(TaskStatus::Done, $assigned->fresh()->status);
        $this->assertFalse($student->can('delete', $assigned));
        $this->assertFalse($student->can('update', $other));
    }

    public function test_overdue_filter_and_pending_users_tab(): void
    {
        $student = User::factory()->create();
        $late = Task::factory()->for($student)->create(['due_date' => today()->subDay()]);
        $upcoming = Task::factory()->for($student)->create(['due_date' => today()->addDay()]);

        $this->actingAs($student);
        Filament::setCurrentPanel('app');

        Livewire::test(StudentTasks::class)
            ->filterTable('overdue', true)
            ->assertCanSeeTableRecords([$late])
            ->assertCanNotSeeTableRecords([$upcoming])
            ->filterTable('overdue', false)
            ->assertCanSeeTableRecords([$upcoming])
            ->assertCanNotSeeTableRecords([$late]);

        $admin = User::factory()->superAdmin()->create();
        $pending = User::factory()->pending()->create();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::withQueryParams(['tab' => 'pending'])
            ->test(ManageUsers::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$student]);
    }

    public function test_completion_timestamp_and_on_time_rate(): void
    {
        $student = User::factory()->create();
        $onTime = Task::factory()->for($student)->create(['due_date' => today()->addDay()]);
        $late = Task::factory()->for($student)->create(['due_date' => today()->subDays(3)]);

        $onTime->update(['status' => TaskStatus::Done]);
        $this->assertNotNull($onTime->fresh()->completed_at);
        $late->update(['status' => TaskStatus::Done]);

        $summary = app(PerformanceReport::class)->summary($student->id);
        $this->assertSame(100.0, $summary['completion_rate']);
        $this->assertSame(50.0, $summary['on_time_rate']);

        $onTime->update(['status' => TaskStatus::Todo]);
        $this->assertNull($onTime->fresh()->completed_at);
    }
}
