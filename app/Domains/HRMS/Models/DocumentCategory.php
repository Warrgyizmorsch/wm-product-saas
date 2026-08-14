<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentCategory extends BaseModel
{
    protected $table = 'document_categories';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'description',
    ];

    /**
     * Get the company this category belongs to.
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\HRMS\Models\Company::class, 'company_id');
    }

    /**
     * Get the document masters belonging to this category.
     */
    public function documentMasters(): HasMany
    {
        return $this->hasMany(DocumentMaster::class, 'document_category_id');
    }
}
