<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Vendor;

/**
 * A request to change the bank details of a verified vendor (FRS 2.3, C-16).
 */
class PartyBankChangeRequest extends BaseModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'party_bank_change_requests';

    protected $fillable = [
        'tenant_id', 'party_type', 'party_id', 'status',
        'old_bank_name', 'old_account_number', 'old_ifsc_code',
        'new_bank_name', 'new_account_number', 'new_ifsc_code',
        'reason', 'phone_on_file',
        'requested_by', 'requested_at',
        'callback_by', 'callback_at', 'callback_number', 'callback_contact', 'callback_notes',
        'decided_by', 'decided_at', 'decision_remarks', 'applied_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'callback_at' => 'datetime',
        'decided_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'party_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function callbackUser()
    {
        return $this->belongsTo(User::class, 'callback_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
