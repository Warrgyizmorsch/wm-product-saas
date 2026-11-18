<?php

namespace App\Domains\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LeadDocument extends Model
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'lead_id',
        'file_name',
        'file_type',
        'file_path',
        'size',
    ];

    /** File types accepted for lead / deal documents (no HTML, SVG, scripts or executables). */
    public const ALLOWED_EXTENSIONS = 'pdf,jpg,jpeg,png,webp,gif,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip';

    /** Validation rules shared by every lead / deal document upload. */
    public static function uploadRules(): array
    {
        return [
            'documents' => ['required', 'array', 'max:10'],
            'documents.*' => ['file', 'max:10240', 'mimes:'.self::ALLOWED_EXTENSIONS],
        ];
    }

    /**
     * New uploads live on the private "local" disk. Files uploaded before that
     * change may still sit on the public disk until crm:privatize-lead-documents runs.
     */
    public function storageDisk(): ?string
    {
        foreach (['local', 'public'] as $disk) {
            if ($this->file_path && \Illuminate\Support\Facades\Storage::disk($disk)->exists($this->file_path)) {
                return $disk;
            }
        }

        return null;
    }

    public function lead(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
