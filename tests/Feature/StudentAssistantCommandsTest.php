<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Services\Assistant\StudentAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentAssistantCommandsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-11 09:00:00');
        $this->user = User::factory()->create();
    }

    private function say(string $text): string
    {
        return app(StudentAssistant::class)->reply($this->user, $text);
    }

    public function test_task_command_creates_a_task_with_date_and_subject(): void
    {
        $reply = $this->say('/task Assignment Fizik | 15/10 | Fizik');

        $task = $this->user->tasks()->sole();
        $this->assertSame('Assignment Fizik', $task->title);
        $this->assertSame('Fizik', $task->subject);
        $this->assertSame('2026-10-15', $task->due_date->toDateString());
        $this->assertNull($task->assigned_by);
        $this->assertStringContainsString('Task ditambah', $reply);
    }

    public function test_task_dates_accept_relative_words_and_roll_past_dates_to_next_year(): void
    {
        $this->say('/task Baca bab 2 | esok');
        $this->say('/task Daftar subjek | 05/01');
        $this->say('/task Tanpa tarikh');

        $dates = $this->user->tasks()->orderBy('id')->get()->map(fn (Task $t) => $t->due_date?->toDateString())->all();
        $this->assertSame(['2026-10-12', '2027-01-05', null], $dates);
    }

    public function test_invalid_task_input_creates_nothing(): void
    {
        $this->assertStringContainsString('Format', $this->say('/task'));
        $this->assertStringContainsString('tidak difahami', $this->say('/task Ujian | 31/02'));
        $this->assertStringContainsString('tidak difahami', $this->say('/task Ujian | minggu depan'));
        $this->assertSame(0, Task::count());
    }

    public function test_todo_command_adds_for_today(): void
    {
        $this->say('/todo Beli kertas A4');

        $todo = $this->user->todos()->sole();
        $this->assertSame('Beli kertas A4', $todo->title);
        $this->assertSame('2026-10-11', $todo->for_date->toDateString());
        $this->assertStringContainsString('Format', $this->say('/todo'));
    }

    public function test_done_marks_the_numbered_item_from_today(): void
    {
        $task = Task::factory()->for($this->user)->create(['title' => 'Lab report', 'due_date' => '2026-10-11']);
        $todo = Todo::create(['user_id' => $this->user->id, 'title' => 'Basuh baju', 'for_date' => '2026-10-11']);

        $list = $this->say('/today');
        $this->assertStringContainsString('1. Lab report', $list);
        $this->assertStringContainsString('2. Basuh baju', $list);

        $this->assertStringContainsString('To-do siap: Basuh baju', $this->say('/done 2'));
        $this->assertTrue($todo->fresh()->is_done);

        $this->assertStringContainsString('Task siap: Lab report', $this->say('/done 1'));
        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_done_rejects_bad_numbers_and_never_touches_other_students(): void
    {
        $other = Task::factory()->create(['due_date' => '2026-10-11']);

        $this->assertStringContainsString('Tiada item no. 1', $this->say('/done 1'));
        $this->assertStringContainsString('Format', $this->say('/done abc'));
        $this->assertSame(TaskStatus::Todo, $other->fresh()->status);
    }
}
