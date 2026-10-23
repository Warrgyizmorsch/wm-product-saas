<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

/**
 * Roster alias / root model for HRMS Shift Roster module policies & authorization.
 */
class Roster extends BaseModel
{
    protected $table = 'shift_rosters';
}
