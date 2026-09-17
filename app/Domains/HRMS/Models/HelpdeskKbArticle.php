<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskKbArticle extends BaseModel
{
    protected $table = 'helpdesk_kb_articles';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'title',
        'slug',
        'content',
        'view_count',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'view_count'   => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpdeskCategory::class, 'category_id');
    }
}
