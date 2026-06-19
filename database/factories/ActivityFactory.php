<?php

namespace Database\Factories;

use App\Enums\ActivityKind;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kind = fake()->randomElement(ActivityKind::cases());
        $dueAt = fake()->dateTimeBetween('-1 week', '+2 weeks');
        $completed = fake()->boolean(60);

        return [
            'subject_type' => Lead::class,
            'subject_id' => Lead::factory(),
            'user_id' => User::factory(),
            'kind' => $kind->value,
            'summary' => $this->summaryFor($kind),
            'note' => fake()->optional()->sentence(),
            'due_at' => $dueAt,
            'completed_at' => $completed ? $dueAt : null,
        ];
    }

    protected function summaryFor(ActivityKind $kind): string
    {
        return match ($kind) {
            ActivityKind::Call => 'Followed up on proposal',
            ActivityKind::Email => 'Sent proposal',
            ActivityKind::Meeting => 'Site walk-through',
            ActivityKind::Note => 'Client called to discuss timing',
            ActivityKind::Task => 'Send proposal by Friday',
            ActivityKind::SiteVisit => 'Toured the Grand Ballroom',
        };
    }
}
