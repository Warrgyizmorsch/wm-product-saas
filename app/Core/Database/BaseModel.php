<?php

namespace App\Core\Database;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasRbacScope;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use BelongsToTenant;
    use HasRbacScope;
}
