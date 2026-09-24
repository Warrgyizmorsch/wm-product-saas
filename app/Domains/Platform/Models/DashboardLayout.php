<?php

namespace App\Domains\Platform\Models;

use App\Core\Database\BaseModel;

/**
 * One saved arrangement of dashboard widgets. user_id set = a personal layout,
 * role_id set = the default for that role, neither = the tenant default.
 */
class DashboardLayout extends BaseModel
{
    protected $table = 'dashboard_layouts';

    protected $fillable = ['tenant_id', 'dashboard', 'user_id', 'role_id', 'widgets'];

    protected $casts = ['widgets' => 'array'];
}
