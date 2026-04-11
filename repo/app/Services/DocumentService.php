<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Doctor;
use App\Models\DoctorDocument;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentService
{
    private const MAX_BYTES    = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/png'];
    private const ALLOWED_EXT  = ['pdf', 'jpeg', 'jpg', 'png'];

    /**
     * Validate, store, and record a document upload.
     *
     * CRED-02: PDF/JPEG/PNG only, max 10 MB.
     * CRED-03: SHA-256 duplicate check per doctor + doc_type.
     *
     * @throws RuntimeException (HTTP 422) on validation failure
     */
    public function upload(
        Doctor       $doctor,
        UploadedFile $file,
        DocumentType $type,
        User         $uploader,
    ): DoctorDocument {
        // ── File type validation ──────────────────────────────────────────────
        $mime = $file->getMimeType();
        $ext  = strtolower($file->getClientOriginalExtension());

        if (! in_array($mime, self::ALLOWED_MIME, true) || ! in_array($ext, self::ALLOWED_EXT, true)) {
            throw new RuntimeException('Only PDF, JPEG, and PNG files are accepted.', 422);
        }

        // ── File size validation ──────────────────────────────────────────────
        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('File size must not exceed 10 MB.', 422);
        }

        // ── Checksum ──────────────────────────────────────────────────────────
        $checksum = hash_file('sha256', $file->getRealPath());

        // CRED-03: reject duplicate checksum for same doctor + type
        if (DoctorDocument::where('doctor_id', $doctor->id)
                ->where('document_type', $type->value)
                ->where('checksum', $checksum)
                ->exists()
        ) {
            throw new RuntimeException('A document with identical content already exists for this type.', 422);
        }

        // ── Store file ────────────────────────────────────────────────────────
        $uuid     = (string) Str::uuid();
        $filename = "{$uuid}.{$ext}";
        $path     = "documents/{$doctor->id}/{$filename}";

        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        // ── Record ────────────────────────────────────────────────────────────
        $doc = DoctorDocument::create([
            'doctor_id'     => $doctor->id,
            'document_type' => $type->value,
            'file_path'     => $path,
            'file_name'     => $file->getClientOriginalName(),
            'file_size'     => $file->getSize(),
            'mime_type'     => $mime,
            'checksum'      => $checksum,
            'uploaded_by'   => $uploader->id,
        ]);

        AuditService::record(
            'doctor_document.uploaded',
            'DoctorDocument',
            $doc->id,
            null,
            ['type' => $type->value, 'doctor_id' => $doctor->id],
        );

        return $doc;
    }

    /**
     * Determine whether a user may upload documents on behalf of a doctor.
     *
     * Object-level authorization rules (Audit Issue 5):
     *  - Admins: always permitted.
     *  - Credentialing reviewers: permitted only when they are the assigned
     *    reviewer on the doctor's active (non-terminal) case.
     *  - Everyone else (including the doctor themselves): use DoctorProfile.
     */
    public function canUploadFor(Doctor $doctor, User $actor): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if (! $actor->isCredentialingReviewer()) {
            return false;
        }

        $activeCase = $doctor->activeCase();

        return $activeCase !== null
            && $activeCase->assigned_reviewer === $actor->id
            && ! $activeCase->status->isTerminal();
    }

    /**
     * Determine whether a user may download a document.
     * Allowed: the doctor themselves, the assigned reviewer, or any admin.
     */
    public function canDownload(DoctorDocument $doc, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Is the user the doctor?
        if ($doc->doctor->user_id === $user->id) {
            return true;
        }

        // Is the user the assigned reviewer on the active case?
        $activeCase = $doc->doctor->activeCase();
        if ($activeCase && $activeCase->assigned_reviewer === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Authorize, audit, and return a download response for a doctor document.
     *
     * Every download call emits a `doctor_document.downloaded` audit entry
     * (audit Issue 5 — export traceability). Authorization is enforced here
     * so the route is a one-liner and no caller can emit the file without
     * passing through the audit hook.
     *
     * @throws RuntimeException 403 if the user is not permitted to download,
     *                          404 if the underlying file is missing.
     */
    public function download(DoctorDocument $doc, User $user): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! $this->canDownload($doc, $user)) {
            throw new RuntimeException('You are not authorised to download this document.', 403);
        }

        if (! Storage::disk('local')->exists($doc->file_path)) {
            throw new RuntimeException('File not found.', 404);
        }

        AuditService::record(
            action:     'doctor_document.downloaded',
            entityType: 'DoctorDocument',
            entityId:   $doc->id,
            before:     null,
            after:      [
                'doctor_id'     => $doc->doctor_id,
                'document_type' => $doc->document_type instanceof \BackedEnum
                                    ? $doc->document_type->value
                                    : $doc->document_type,
                'file_name'     => $doc->file_name,
                'checksum'      => $doc->checksum,
            ],
        );

        return Storage::disk('local')->download($doc->file_path, $doc->file_name, [
            'Content-Type' => $doc->mime_type,
        ]);
    }
}
