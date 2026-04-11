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
