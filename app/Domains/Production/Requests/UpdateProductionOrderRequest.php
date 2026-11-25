<?php

namespace App\Domains\Production\Requests;

/**
 * M-34: the Edit screen is the Add form, so saving it is checked exactly like creating — product, sales request, BOM / routing
 * blocking checks, operation / material choices, project links, quantity and dates. (The REST API keeps its own request class.)
 */
class UpdateProductionOrderRequest extends StoreProductionOrderRequest
{
}
