<?php

namespace Database\Factories;

use App\Enums\CaseStatus;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CredentialingCase>
 */
class CredentialingCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id'                => Str::uuid(),
            'doctor_id'         => Doctor::factory(),
            'status'            => CaseStatus::SUBMITTED,
            'assigned_reviewer' => null,
            'submitted_at'      => now(),
            'resolved_at'       => null,
            'version'           => 1,
        ];
    }

    public function inReview(): static
    {
        return $this->state(['status' => CaseStatus::INITIAL_REVIEW]);
    }

    public function moreMaterialsRequested(): static
    {
        return $this->state(['status' => CaseStatus::MORE_MATERIALS_REQUESTED]);
    }

    public function reReview(): static
    {
        return $this->state(['status' => CaseStatus::RE_REVIEW]);
    }

    public function approved(): static
    {
        return $this->state([
            'status'      => CaseStatus::APPROVED,
            'resolved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status'      => CaseStatus::REJECTED,
            'resolved_at' => now(),
        ]);
    }
}
