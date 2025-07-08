<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\Student;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Complaint>
 */
class ComplaintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'exam_id' => Exam::factory(),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['pending', 'resolved', 'rejected']),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'updated_by' => null,
            'resolved_by' => null,
            'rejected_by' => null,
        ];
    }

    /**
     * Indicate that the complaint is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the complaint is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'resolved_by' => User::factory(),
        ]);
    }

    /**
     * Indicate that the complaint is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'rejected_by' => User::factory(),
        ]);
    }
}
