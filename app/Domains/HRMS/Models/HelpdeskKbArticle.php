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

    /**
     * Get clean plain-text content without raw HTML tags for form editing
     */
    public function getCleanContentAttribute(): string
    {
        if (empty($this->content)) {
            return '';
        }

        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $this->content);
        $text = preg_replace('/<\/\s*(p|div|h[1-6]|tr)\s*>/i', "\n\n", $text);
        $text = preg_replace('/<\/\s*li\s*>/i', "\n", $text);
        $text = preg_replace('/<\s*li\s*>/i', "• ", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Get clean decoded title without double-encoded entities for form editing
     */
    public function getCleanTitleAttribute(): string
    {
        return html_entity_decode($this->title ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
