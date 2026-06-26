<?php

namespace App\Core\Database;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use BelongsToTenant;
}
