<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    protected $table = 'email_attachments';

    protected $fillable = [
        'email_message_id', 'file_name', 'file_path', 'mime_type', 'file_size',
        'is_inline', 'content_id'
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
