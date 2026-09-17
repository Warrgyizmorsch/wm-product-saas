<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppChatSession extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_chat_sessions';

    protected $fillable = [
        'tenant_id',
        'session_key',
        'phone',
        'sender_name',
        'current_step',
        'category',
        'collected_data',
        'is_completed',
    ];

    protected $casts = [
        'collected_data' => 'array',
        'is_completed'   => 'boolean',
        'current_step'   => 'integer',
    ];
}
