<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Credential;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('crm.super_admin.email');
        $password = config('crm.super_admin.password');

        if (blank($email)) {
            $this->command?->warn('SUPER_ADMIN_EMAIL not set; skipping super admin seeding.');
        } elseif (! User::where('email', $email)->exists()) {
            $generated = blank($password);
            $password = $generated ? Str::password(16) : $password;

            User::forceCreate([
                'name' => config('crm.super_admin.name'),
                'email' => $email,
                'password' => $password,
                'role' => UserRole::SuperAdmin,
                'status' => UserStatus::Approved,
                'approved_at' => now(),
                'email_verified_at' => now(),
            ]);

            $this->command?->info("Super admin created: {$email}");
            if ($generated) {
                $this->command?->warn("Generated password (change it after first login): {$password}");
            }
        }

        if (app()->environment('local')) {
            $this->seedDemoStudent();
        }
    }

    private function seedDemoStudent(): void
    {
        if (User::where('email', 'pelajar@example.com')->exists()) {
            return;
        }

        $student = User::factory()->create([
            'name' => 'Pelajar Demo',
            'email' => 'pelajar@example.com',
            'student_id' => 'STU0001',
            'programme' => 'Diploma Sains Komputer',
            'semester' => 3,
        ]);

        Task::factory()->count(6)->for($student)->create();
        Task::factory()->count(4)->for($student)->done()->create();

        Todo::create(['user_id' => $student->id, 'title' => 'Ulangkaji bab 3', 'for_date' => today()]);
        Todo::create(['user_id' => $student->id, 'title' => 'Hantar borang kokurikulum', 'for_date' => today(), 'is_done' => true]);

        Credential::create([
            'user_id' => $student->id,
            'label' => 'Email college',
            'category' => 'email',
            'url' => 'https://mail.example.edu.my',
            'username' => 'stu0001@example.edu.my',
            'password' => 'demo-password',
        ]);

        User::factory()->pending()->create(['name' => 'Pelajar Baru', 'email' => 'baru@example.com']);
    }
}
