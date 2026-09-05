<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends BaseModel
{
    protected $table = 'document_templates';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'document_category_id',
        'name',
        'code',
        'template_file_path',
        'header_content',
        'body_content',
        'footer_content',
        'css_styles',
        'is_default',
        'status',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function generatedDocuments(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class, 'document_template_id');
    }
}
