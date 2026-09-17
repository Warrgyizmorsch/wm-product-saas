<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskTicketReply extends BaseModel
{
    protected $table = 'helpdesk_ticket_replies';

    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'sender_id',
        'message',
        'is_internal_note',
    ];

    protected $casts = [
        'is_internal_note' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'ticket_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'sender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HelpdeskTicketAttachment::class, 'reply_id');
    }
}
