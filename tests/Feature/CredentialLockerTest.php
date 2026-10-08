<?php

namespace Tests\Feature;

use App\Filament\App\Resources\Credentials\CredentialResource;
use App\Filament\App\Resources\Credentials\Pages\ManageCredentials;
use App\Models\Credential;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CredentialLockerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
    }

    public function test_password_and_notes_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $credential = Credential::create([
            'user_id' => $user->id, 'label' => 'Email', 'category' => 'email',
            'password' => 'plain-secret-42', 'notes' => 'recovery code 1234',
        ]);

        $raw = DB::table('credentials')->where('id', $credential->id)->first();

        $this->assertStringNotContainsString('plain-secret-42', $raw->password);
        $this->assertStringNotContainsString('1234', $raw->notes);
        $this->assertSame('plain-secret-42', $credential->fresh()->password);
        $this->assertArrayNotHasKey('password', $credential->fresh()->toArray());
    }

    public function test_students_only_see_their_own_locker_entries(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $mine = Credential::create(['user_id' => $alice->id, 'label' => 'Mine', 'password' => 'a']);
        $theirs = Credential::create(['user_id' => $bob->id, 'label' => 'Theirs', 'password' => 'b']);

        $this->actingAs($alice);

        Livewire::test(ManageCredentials::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_super_admin_cannot_read_a_students_credentials(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $student = User::factory()->create();
        $credential = Credential::create(['user_id' => $student->id, 'label' => 'Portal', 'password' => 'x']);

        $this->assertFalse($admin->can('view', $credential));
        $this->assertFalse($admin->can('update', $credential));

        $this->actingAs($admin);
        Livewire::test(ManageCredentials::class)->assertCanNotSeeTableRecords([$credential]);
    }

    public function test_reveal_requires_unlocking_with_the_account_password(): void
    {
        $user = User::factory()->create();
        $credential = Credential::create(['user_id' => $user->id, 'label' => 'LMS', 'password' => 'lms-pass']);
        $this->actingAs($user);

        Livewire::test(ManageCredentials::class)
            ->assertTableActionHidden('reveal', $credential)
            ->callAction('unlock', ['current_password' => 'wrong'])
            ->assertHasActionErrors(['current_password']);

        $this->assertFalse(CredentialResource::isUnlocked());

        Livewire::test(ManageCredentials::class)
            ->callAction('unlock', ['current_password' => 'password'])
            ->assertHasNoActionErrors();

        $this->assertTrue(CredentialResource::isUnlocked());

        Livewire::test(ManageCredentials::class)
            ->assertTableActionVisible('reveal', $credential)
            ->mountTableAction('reveal', $credential)
            ->assertTableActionDataSet(['password' => 'lms-pass']);
    }

    public function test_creating_an_entry_assigns_it_to_the_current_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ManageCredentials::class)
            ->callAction('create', ['label' => 'Wifi', 'category' => 'web', 'password' => 'wifi-pass'])
            ->assertHasNoActionErrors();

        $this->assertSame($user->id, Credential::firstWhere('label', 'Wifi')->user_id);
    }
}
