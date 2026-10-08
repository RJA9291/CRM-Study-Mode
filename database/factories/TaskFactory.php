<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'subject' => fake()->randomElement(['Matematik', 'Fizik', 'Pengaturcaraan', 'Bahasa Inggeris', 'Pengurusan']),
            'due_date' => fake()->dateTimeBetween('-10 days', '+14 days'),
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'status' => TaskStatus::Todo,
        ];
    }

    public function done(?string $completedAt = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskStatus::Done,
            'completed_at' => $completedAt ?? now(),
        ]);
    }
}
