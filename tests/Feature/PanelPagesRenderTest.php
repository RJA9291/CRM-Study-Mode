<?php

namespace Tests\Feature;

use App\Models\Credential;
use App\Models\KnowledgeSource;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_student_page_renders(): void
    {
        $student = User::factory()->create();
        Task::factory()->for($student)->count(3)->create();
        Todo::create(['user_id' => $student->id, 'title' => 'Baca nota', 'for_date' => today()]);
        Credential::create(['user_id' => $student->id, 'label' => 'Portal', 'password' => 'x']);

        foreach (['/app', '/app/tasks', '/app/todos', '/app/locker', '/app/chat-assistant', '/app/profile'] as $url) {
            $this->actingAs($student)->get($url)->assertOk();
        }
    }

    public function test_every_admin_page_renders(): void
    {
        $admin = User::factory()->superAdmin()->create();
        User::factory()->pending()->create();
        Task::factory()->count(3)->create();
        KnowledgeSource::create(['name' => 'Nota', 'drive_url' => 'https://drive.google.com/drive/folders/FOLDER123456']);

        foreach (['/admin', '/admin/users', '/admin/tasks', '/admin/knowledge', '/admin/chat-messages', '/admin/profile'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_guests_can_open_login_and_register(): void
    {
        $this->get('/app/login')->assertOk();
        $this->get('/app/register')->assertOk();
        $this->get('/admin/login')->assertOk();
        $this->get('/admin/register')->assertNotFound();
    }
}
