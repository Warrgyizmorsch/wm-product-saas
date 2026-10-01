<?php

namespace App\Domains\Visitor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToBranch;

class Visitor extends Model
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'visitors';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'visitor_code',
        'full_name',
        'phone',
        'email',
        'company_name',
        'designation',
        'id_proof_type',
        'id_proof_number',
        'photo_url',
        'status',
        'is_blacklisted',
        'blacklist_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_blacklisted' => 'boolean',
            'metadata'       => 'array',
        ];
    }

    public function passes(): HasMany
    {
        return $this->hasMany(VisitorPass::class, 'visitor_id');
    }
}
