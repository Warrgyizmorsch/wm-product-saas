<?php
declare(strict_types=1);

namespace App\Domains\Purchase\Models;

use App\Domains\Inventory\Models\Vendor as BaseVendor;

/**
 * Purchase Domain Vendor Model
 * Extends the primary Inventory Vendor model for seamless domain compatibility.
 */
class Vendor extends BaseVendor
{
    // Inherits table, fillable, relationships, and global scopes from BaseVendor
}
