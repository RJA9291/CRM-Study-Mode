<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Auth\Login;
use App\Filament\Auth\Register;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_pending_student_who_is_not_logged_in(): void
    {
        Filament::setCurrentPanel('app');

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Aminah',
                'email' => 'aminah@example.com',
                'student_id' => 'STU123',
                'password' => 'secret-pass-123',
                'passwordConfirmation' => 'secret-pass-123',
            ])
            ->call('register')
            ->assertHasNoFormErrors()
            ->assertRedirect(Filament::getLoginUrl());

        $user = User::where('email', 'aminah@example.com')->firstOrFail();
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertSame('STU123', $user->student_id);
        $this->assertGuest();
    }

    public function test_registration_cannot_self_assign_admin_role(): void
    {
        Filament::setCurrentPanel('app');

        Livewire::test(Register::class)
            ->set('data.role', 'super_admin')
            ->set('data.status', 'approved')
            ->fillForm([
                'name' => 'Sneaky',
                'email' => 'sneaky@example.com',
                'student_id' => 'X1',
                'password' => 'secret-pass-123',
                'passwordConfirmation' => 'secret-pass-123',
            ])
            ->call('register');

        $user = User::where('email', 'sneaky@example.com')->firstOrFail();
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertSame(UserStatus::Pending, $user->status);
    }

    public function test_pending_student_gets_a_clear_message_at_login(): void
    {
        Filament::setCurrentPanel('app');
        User::factory()->pending()->create(['email' => 'p@example.com']);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'p@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_super_admin_can_approve_and_student_can_then_log_in(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $student = User::factory()->pending()->create(['email' => 's@example.com']);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ManageUsers::class)
            ->callTableAction('approve', $student);

        $this->assertSame(UserStatus::Approved, $student->fresh()->status);
        $this->assertNotNull($student->fresh()->approved_at);

        auth()->logout();
        Filament::setCurrentPanel('app');

        Livewire::test(Login::class)
            ->fillForm(['email' => 's@example.com', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($student->fresh());
    }

    public function test_panel_access_is_role_and_status_based(): void
    {
        $student = User::factory()->create();
        $pending = User::factory()->pending()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($student)->get('/admin')->assertForbidden();
        $this->actingAs($student)->get('/app')->assertOk();
        $this->actingAs($pending)->get('/app')->assertForbidden();
        $this->actingAs($admin)->get('/admin')->assertOk();
    }
}
