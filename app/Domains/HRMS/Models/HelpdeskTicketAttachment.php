<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskTicketAttachment extends Model
{
    protected $table = 'helpdesk_ticket_attachments';

    protected $fillable = [
        'ticket_id',
        'reply_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'ticket_id');
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicketReply::class, 'reply_id');
    }
}
