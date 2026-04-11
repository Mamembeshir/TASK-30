<?php

use App\Enums\DocumentType;
use App\Models\Doctor;
use App\Models\DoctorDocument;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function fakePdf(string $name = 'license.pdf', int $kilobytes = 100): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
}

function fakeJpeg(string $name = 'cert.jpg', int $kilobytes = 200): UploadedFile
{
    return UploadedFile::fake()->image($name, 800, 600);
}

// ── Happy path ────────────────────────────────────────────────────────────────

it('uploads a valid PDF and stores record', function () {
    $doctor  = Doctor::factory()->create();
    $uploader = User::factory()->create();
    $svc     = new DocumentService();

    $doc = $svc->upload($doctor, fakePdf(), DocumentType::LICENSE, $uploader);

    expect($doc->id)->not->toBeNull();
    expect($doc->document_type)->toBe(DocumentType::LICENSE);
    expect($doc->uploaded_by)->toBe($uploader->id);
    expect(Storage::disk('local')->exists($doc->file_path))->toBeTrue();
});

it('uploads a valid JPEG', function () {
    $doctor  = Doctor::factory()->create();
    $svc     = new DocumentService();

    $doc = $svc->upload($doctor, fakeJpeg(), DocumentType::BOARD_CERTIFICATION, User::factory()->create());

    expect($doc->mime_type)->toBe('image/jpeg');
});

// ── File type validation (CRED-02) ────────────────────────────────────────────

it('rejects .exe file → 422', function () {
    $doctor = Doctor::factory()->create();
    $file   = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');
    $svc    = new DocumentService();

    expect(fn () => $svc->upload($doctor, $file, DocumentType::OTHER, User::factory()->create()))
        ->toThrow(\RuntimeException::class, 'Only PDF, JPEG, and PNG files are accepted.');
});

it('rejects a PDF with a .txt extension', function () {
    $doctor = Doctor::factory()->create();
    $file   = UploadedFile::fake()->create('trick.txt', 50, 'application/pdf');
    $svc    = new DocumentService();

    expect(fn () => $svc->upload($doctor, $file, DocumentType::OTHER, User::factory()->create()))
        ->toThrow(\RuntimeException::class);
});

// ── File size validation (CRED-02) ────────────────────────────────────────────

it('rejects file larger than 10 MB → 422', function () {
    $doctor = Doctor::factory()->create();
    // 10241 KB = ~10.001 MB
    $file   = UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf');
    $svc    = new DocumentService();

    expect(fn () => $svc->upload($doctor, $file, DocumentType::LICENSE, User::factory()->create()))
        ->toThrow(\RuntimeException::class, 'File size must not exceed 10 MB.');
});

it('accepts exactly 10 MB', function () {
    $doctor = Doctor::factory()->create();
    $file   = UploadedFile::fake()->create('exact.pdf', 10240, 'application/pdf');
    $svc    = new DocumentService();

    $doc = $svc->upload($doctor, $file, DocumentType::LICENSE, User::factory()->create());
    expect($doc)->not->toBeNull();
});

// ── Duplicate checksum (CRED-03) ──────────────────────────────────────────────

it('rejects duplicate checksum for same doctor and document type → 422', function () {
    $doctor  = Doctor::factory()->create();
    $uploader = User::factory()->create();
    $svc     = new DocumentService();

    // Create a real temp file so checksum is deterministic
    $tmpPath = tempnam(sys_get_temp_dir(), 'test') . '.pdf';
    file_put_contents($tmpPath, '%PDF-1.4 fake content for test');
    $checksum = hash_file('sha256', $tmpPath);

    // Pre-insert a doc with the same checksum
    DoctorDocument::factory()->create([
        'doctor_id'     => $doctor->id,
        'document_type' => DocumentType::LICENSE->value,
        'checksum'      => $checksum,
    ]);

    $file = new UploadedFile($tmpPath, 'license.pdf', 'application/pdf', null, true);

    expect(fn () => $svc->upload($doctor, $file, DocumentType::LICENSE, $uploader))
        ->toThrow(\RuntimeException::class, 'identical content already exists');

    @unlink($tmpPath);
});

it('allows same checksum for different document types', function () {
    $doctor   = Doctor::factory()->create();
    $uploader = User::factory()->create();
    $svc      = new DocumentService();

    $tmpPath = tempnam(sys_get_temp_dir(), 'test') . '.pdf';
    file_put_contents($tmpPath, '%PDF-1.4 shared content');
    $checksum = hash_file('sha256', $tmpPath);

    DoctorDocument::factory()->create([
        'doctor_id'     => $doctor->id,
        'document_type' => DocumentType::LICENSE->value,
        'checksum'      => $checksum,
    ]);

    $file = new UploadedFile($tmpPath, 'cert.pdf', 'application/pdf', null, true);

    $doc = $svc->upload($doctor, $file, DocumentType::BOARD_CERTIFICATION, $uploader);
    expect($doc)->not->toBeNull();

    @unlink($tmpPath);
});

// ── Download authorization + audit (Audit Issue 5) ───────────────────────────
//
// Every download of a doctor document must be audit-logged for traceability.
// Prior to the fix, the credentialing download route in `routes/web.php`
// streamed the file without ever touching the audit log.

it('download records an audit entry for the doctor', function () {
    $doctor   = Doctor::factory()->create();
    $owner    = \App\Models\User::find($doctor->user_id);
    $svc      = new DocumentService();

    // Audit actor_id is sourced from auth()->id() inside AuditService::record(),
    // so we must authenticate before invoking the service or the actor column
    // will be null.
    $this->actingAs($owner);

    $doc = $svc->upload($doctor, fakePdf(), DocumentType::LICENSE, $owner);

    $svc->download($doc->fresh(), $owner);

    $audit = \App\Models\AuditLog::where('action', 'doctor_document.downloaded')
        ->where('entity_id', $doc->id)
        ->latest('created_at')
        ->first();

    // $doctor->id comes back as a LazyUuidFromString object from Eloquent's
    // uuid cast; after_data round-trips through jsonb as a plain string. Cast
    // both sides to string for the strict ===-based toBe() comparison.
    expect($audit)->not->toBeNull()
        ->and($audit->actor_id)->toBe($owner->id)
        ->and((string) $audit->after_data['doctor_id'])->toBe((string) $doctor->id)
        ->and($audit->after_data['document_type'])->toBe(DocumentType::LICENSE->value);
});

it('download throws 403 (no audit) when the user is not permitted', function () {
    $doctor   = Doctor::factory()->create();
    $stranger = \App\Models\User::factory()->create();
    $svc      = new DocumentService();

    $doc = $svc->upload($doctor, fakePdf(), DocumentType::LICENSE, \App\Models\User::find($doctor->user_id));

    expect(fn () => $svc->download($doc->fresh(), $stranger))
        ->toThrow(\RuntimeException::class, 'not authorised');

    // Forbidden attempts must not leave a download audit entry — otherwise
    // unauthorized probes would pollute the traceability record. Only the
    // upload entry should exist.
    expect(\App\Models\AuditLog::where('action', 'doctor_document.downloaded')->count())->toBe(0);
});

// ── canUploadFor authorization (Audit Issue 5) ────────────────────────────────
//
// Credentialing staff upload path must enforce object-level authorization:
// only the assigned reviewer on an active case, or an admin, may upload on
// behalf of a doctor. Any other actor (plain member, unassigned reviewer,
// reviewer whose case is terminal) must be refused.

it('canUploadFor: admin is always permitted', function () {
    $doctor = Doctor::factory()->create();
    $admin  = \App\Models\User::factory()->create();
    $admin->roles()->create(['role' => \App\Enums\UserRole::ADMIN->value, 'assigned_at' => now()]);

    expect((new DocumentService())->canUploadFor($doctor, $admin->fresh()))->toBeTrue();
});

it('canUploadFor: assigned reviewer with an active non-terminal case is permitted', function () {
    $doctor   = Doctor::factory()->create();
    $reviewer = \App\Models\User::factory()->create();
    $reviewer->roles()->create(['role' => \App\Enums\UserRole::CREDENTIALING_REVIEWER->value, 'assigned_at' => now()]);

    \App\Models\CredentialingCase::factory()->create([
        'doctor_id'         => $doctor->id,
        'assigned_reviewer' => $reviewer->id,
        'status'            => \App\Enums\CaseStatus::INITIAL_REVIEW->value,
    ]);

    expect((new DocumentService())->canUploadFor($doctor, $reviewer->fresh()))->toBeTrue();
});

it('canUploadFor: unassigned reviewer is denied', function () {
    $doctor             = Doctor::factory()->create();
    $assignedReviewer   = \App\Models\User::factory()->create();
    $unassignedReviewer = \App\Models\User::factory()->create();

    foreach ([$assignedReviewer, $unassignedReviewer] as $u) {
        $u->roles()->create(['role' => \App\Enums\UserRole::CREDENTIALING_REVIEWER->value, 'assigned_at' => now()]);
    }

    \App\Models\CredentialingCase::factory()->create([
        'doctor_id'         => $doctor->id,
        'assigned_reviewer' => $assignedReviewer->id,
        'status'            => \App\Enums\CaseStatus::INITIAL_REVIEW->value,
    ]);

    expect((new DocumentService())->canUploadFor($doctor, $unassignedReviewer->fresh()))->toBeFalse();
});

it('canUploadFor: reviewer is denied when the case is terminal (APPROVED)', function () {
    $doctor   = Doctor::factory()->approved()->create();
    $reviewer = \App\Models\User::factory()->create();
    $reviewer->roles()->create(['role' => \App\Enums\UserRole::CREDENTIALING_REVIEWER->value, 'assigned_at' => now()]);

    \App\Models\CredentialingCase::factory()->create([
        'doctor_id'         => $doctor->id,
        'assigned_reviewer' => $reviewer->id,
        'status'            => \App\Enums\CaseStatus::APPROVED->value,
    ]);

    expect((new DocumentService())->canUploadFor($doctor, $reviewer->fresh()))->toBeFalse();
});

it('canUploadFor: plain member is always denied', function () {
    $doctor = Doctor::factory()->create();
    $member = \App\Models\User::factory()->create();
    $member->roles()->create(['role' => \App\Enums\UserRole::MEMBER->value, 'assigned_at' => now()]);

    expect((new DocumentService())->canUploadFor($doctor, $member->fresh()))->toBeFalse();
});

// ── Staff upload via CaseDetail Livewire component ────────────────────────────
//
// Regression coverage for the "staff upload path missing" audit finding.
// Verifies that the upload action in CaseDetail:
//  a) persists the document attributed to the staff actor
//  b) is blocked by object-level authorization for unassigned reviewers

it('CaseDetail: assigned reviewer can upload a document for the case doctor', function () {
    Storage::fake('local');

    $doctor   = Doctor::factory()->create();
    $reviewer = \App\Models\User::factory()->create();
    $reviewer->roles()->create(['role' => \App\Enums\UserRole::CREDENTIALING_REVIEWER->value, 'assigned_at' => now()]);

    $case = \App\Models\CredentialingCase::factory()->create([
        'doctor_id'         => $doctor->id,
        'assigned_reviewer' => $reviewer->id,
        'status'            => \App\Enums\CaseStatus::INITIAL_REVIEW->value,
    ]);

    \Livewire\Livewire::actingAs($reviewer->fresh())
        ->test(\App\Livewire\Credentialing\CaseDetail::class, ['case' => $case])
        ->set('staffUploadFile', \Illuminate\Http\UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'))
        ->set('staffUploadType', \App\Enums\DocumentType::LICENSE->value)
        ->call('uploadDocument')
        ->assertHasNoErrors();

    $doc = \App\Models\DoctorDocument::where('doctor_id', $doctor->id)->first();
    expect($doc)->not->toBeNull()
        ->and($doc->uploaded_by)->toBe($reviewer->id)
        ->and($doc->document_type)->toBe(\App\Enums\DocumentType::LICENSE);

    // Audit entry must exist for the upload
    $audit = \App\Models\AuditLog::where('action', 'doctor_document.uploaded')
        ->where('entity_id', $doc->id)
        ->first();
    expect($audit)->not->toBeNull();
});

it('CaseDetail: unassigned reviewer is denied upload and no document is created', function () {
    Storage::fake('local');

    $doctor             = Doctor::factory()->create();
    $assignedReviewer   = \App\Models\User::factory()->create();
    $unassignedReviewer = \App\Models\User::factory()->create();

    foreach ([$assignedReviewer, $unassignedReviewer] as $u) {
        $u->roles()->create(['role' => \App\Enums\UserRole::CREDENTIALING_REVIEWER->value, 'assigned_at' => now()]);
    }

    $case = \App\Models\CredentialingCase::factory()->create([
        'doctor_id'         => $doctor->id,
        'assigned_reviewer' => $assignedReviewer->id,
        'status'            => \App\Enums\CaseStatus::INITIAL_REVIEW->value,
    ]);

    \Livewire\Livewire::actingAs($unassignedReviewer->fresh())
        ->test(\App\Livewire\Credentialing\CaseDetail::class, ['case' => $case])
        ->set('staffUploadFile', \Illuminate\Http\UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'))
        ->set('staffUploadType', \App\Enums\DocumentType::LICENSE->value)
        ->call('uploadDocument')
        ->assertHasErrors(['staffUploadFile']);

    expect(\App\Models\DoctorDocument::where('doctor_id', $doctor->id)->count())->toBe(0);
});
