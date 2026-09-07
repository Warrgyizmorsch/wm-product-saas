<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionEcoItem extends BaseModel
{
    use HasFactory;

    protected $table = 'production_eco_items';

    public const ENTITY_BOM = 'BOM';
    public const ENTITY_ROUTING = 'ROUTING';

    public const ACTION_ADD_COMPONENT = 'ADD_COMPONENT';
    public const ACTION_REMOVE_COMPONENT = 'REMOVE_COMPONENT';
    public const ACTION_REPLACE_COMPONENT = 'REPLACE_COMPONENT';
    public const ACTION_CHANGE_QUANTITY = 'CHANGE_QUANTITY';
    public const ACTION_CHANGE_SCRAP_FACTOR = 'CHANGE_SCRAP_FACTOR';

    public const ACTION_ADD_OPERATION = 'ADD_OPERATION';
    public const ACTION_REMOVE_OPERATION = 'REMOVE_OPERATION';
    public const ACTION_CHANGE_OPERATION_SEQUENCE = 'CHANGE_OPERATION_SEQUENCE';
    public const ACTION_CHANGE_WORK_CENTER = 'CHANGE_WORK_CENTER';
    public const ACTION_CHANGE_MACHINE = 'CHANGE_MACHINE';
    public const ACTION_CHANGE_SETUP_TIME = 'CHANGE_SETUP_TIME';
    public const ACTION_CHANGE_RUN_TIME = 'CHANGE_RUN_TIME';

    protected $fillable = [
        'tenant_id',
        'eco_id',
        'entity_type',
        'action_type',
        'target_id',
        'old_value',
        'new_value',
        'notes',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];

    public function eco(): BelongsTo
    {
        return $this->belongsTo(ProductionEco::class, 'eco_id');
    }
}
