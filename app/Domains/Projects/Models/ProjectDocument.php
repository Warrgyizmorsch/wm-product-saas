<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectDocument extends BaseModel
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'project_documents';

    public const CATEGORY_REQUIREMENT = 'Requirement';
    public const CATEGORY_DESIGN = 'Design';
    public const CATEGORY_API = 'API';
    public const CATEGORY_TEST_CASE = 'Test Case';
    public const CATEGORY_MEETING_MINUTES = 'Meeting Minutes';
    public const CATEGORY_ATTACHMENT = 'Attachment';

    public const CATEGORIES = [
        self::CATEGORY_REQUIREMENT,
        self::CATEGORY_DESIGN,
        self::CATEGORY_API,
        self::CATEGORY_TEST_CASE,
        self::CATEGORY_MEETING_MINUTES,
        self::CATEGORY_ATTACHMENT,
    ];

    protected $attributes = [
        'category' => self::CATEGORY_ATTACHMENT,
    ];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'project_id',
        'attachable_type',
        'attachable_id',
        'title',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'category',
        'uploaded_by',
        'remarks',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getHumanFileSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    public function getFileIconAttribute(): string
    {
        $mime = strtolower((string) $this->mime_type);
        if (str_contains($mime, 'pdf')) {
            return 'feather-file-text text-danger';
        }
        if (str_contains($mime, 'image')) {
            return 'feather-image text-primary';
        }
        if (str_contains($mime, 'sheet') || str_contains($mime, 'excel') || str_contains($mime, 'csv')) {
            return 'feather-grid text-success';
        }
        if (str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint')) {
            return 'feather-airplay text-warning';
        }
        if (str_contains($mime, 'word') || str_contains($mime, 'document')) {
            return 'feather-file text-info';
        }
        if (str_contains($mime, 'zip') || str_contains($mime, 'tar') || str_contains($mime, 'compressed')) {
            return 'feather-archive text-secondary';
        }
        return 'feather-file text-muted';
    }
}
