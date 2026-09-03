<?php

namespace App\Domains\CRM\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\CRM\Models\CrmAccount;

class CrmContact extends BaseModel
{
    use HasFactory, SoftDeletes, BelongsToCompany, BelongsToBranch;

    protected $table = 'crm_contacts';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
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
