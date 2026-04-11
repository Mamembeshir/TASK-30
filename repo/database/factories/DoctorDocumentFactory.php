<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DoctorDocument>
 */
class DoctorDocumentFactory extends Factory
{
    public function definition(): array
    {
        $doctorId = Str::uuid();
        $uuid     = Str::uuid();

        return [
            'id'            => $uuid,
            'doctor_id'     => Doctor::factory(),
            'document_type' => DocumentType::LICENSE,
            'file_path'     => "documents/{$doctorId}/{$uuid}.pdf",
            'file_name'     => 'license.pdf',
            'file_size'     => 102400,
            'mime_type'     => 'application/pdf',
            'checksum'      => bin2hex(random_bytes(32)),
            'uploaded_by'   => User::factory(),
        ];
    }

    public function type(DocumentType $type): static
    {
        return $this->state(['document_type' => $type]);
    }
}
