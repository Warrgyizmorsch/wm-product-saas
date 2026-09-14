<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskSatisfactionRating extends Model
{
    protected $table = 'helpdesk_satisfaction_ratings';

    protected $fillable = [
        'ticket_id',
        'rating',
        'feedback',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'ticket_id');
    }
}
