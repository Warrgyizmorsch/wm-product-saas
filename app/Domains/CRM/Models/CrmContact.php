<?php

namespace App\Domains\CRM\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\CRM\Models\CrmAccount;

class CrmContact extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_contacts';

    protected $fillable = [
        'tenant_id',
        'crm_account_id',
        'name',
        'designation',
        'role', // Decision Maker, Technical Evaluator, Finance, Influencer, End User
        'email',
        'phone',
        'mobile',
        'is_primary',
        'status', // active, inactive
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'crm_account_id');
    }
}
