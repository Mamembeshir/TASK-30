<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorDocument extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;
    public const CREATED_AT = 'uploaded_at';

    protected $table = 'doctor_documents';

    protected $fillable = [
        'doctor_id',
        'document_type',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'checksum',
        'uploaded_by',
    ];

    protected $casts = [
        'document_type' => DocumentType::class,
        'file_size'     => 'integer',
        'uploaded_at'   => 'datetime',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function fileSizeHuman(): string
    {
        $kb = $this->file_size / 1024;
        return $kb < 1024
            ? round($kb, 1) . ' KB'
            : round($kb / 1024, 1) . ' MB';
    }
}
