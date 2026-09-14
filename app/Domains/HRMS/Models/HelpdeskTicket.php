<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HelpdeskTicket extends BaseModel
{
    protected $table = 'helpdesk_tickets';

    protected $fillable = [
        'tenant_id',
        'ticket_number',
        'employee_id',
        'category_id',
        'priority',
        'status',
        'subject',
        'description',
        'assigned_to',
        'due_at',
        'resolved_at',
        'closed_at',
        'is_confidential',
    ];

    protected $casts = [
        'due_at'          => 'datetime',
        'resolved_at'     => 'datetime',
        'closed_at'       => 'datetime',
        'is_confidential' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpdeskCategory::class, 'category_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(HelpdeskTicketReply::class, 'ticket_id')->orderBy('created_at', 'asc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(HelpdeskTicketAttachment::class, 'ticket_id');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(HelpdeskSatisfactionRating::class, 'ticket_id');
    }

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && !in_array($this->status, ['resolved', 'closed']);
    }

    public static function generateTicketNumber(int $tenantId): string
    {
        $year = date('Y');
        $latest = static::where('tenant_id', $tenantId)
            ->where('ticket_number', 'like', "TICK-{$year}-%")
            ->latest('id')
            ->first();

        if ($latest && preg_match('/TICK-\d{4}-(\d+)/', $latest->ticket_number, $matches)) {
            $nextSeq = intval($matches[1]) + 1;
        } else {
            $nextSeq = 1;
        }

        return sprintf("TICK-%s-%05d", $year, $nextSeq);
    }
}
