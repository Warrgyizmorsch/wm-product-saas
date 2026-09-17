<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailMessage extends Model
{
    protected $table = 'email_messages';

    protected $fillable = [
        'email_configuration_id', 'thread_id', 'message_id', 'in_reply_to',
        'direction', 'folder', 'from_name', 'from_email', 'to_name', 'to_email',
        'cc', 'bcc', 'reply_to', 'subject', 'body_html', 'body_plain',
        'is_read', 'is_starred', 'is_draft', 'has_attachments', 'raw_headers',
        'customer_email', 'received_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_starred' => 'boolean',
        'is_draft' => 'boolean',
        'has_attachments' => 'boolean',
        'raw_headers' => 'array',
        'received_at' => 'datetime',
    ];

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(EmailConfiguration::class, 'email_configuration_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class, 'email_message_id');
    }

    public static function extractCleanEmail(?string $raw): ?string
    {
        if (!$raw) return null;
        if (preg_match('/<([^>]+)>/', $raw, $m)) return strtolower(trim($m[1]));
        if (filter_var(trim($raw), FILTER_VALIDATE_EMAIL)) return strtolower(trim($raw));
        return null;
    }
}
