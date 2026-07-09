<div class="row mb-4">
    <div class="col-12">
        <div class="bg-white px-4 pt-3 pb-0 rounded shadow-sm border-bottom">
            <ul class="nav nav-tabs nav-tabs-line nav-tabs-primary border-0 fs-13" style="margin-bottom: -1px;">
                <li class="nav-item">
                    <a class="nav-link pb-3 px-3 @if(Route::is('crm.customers.*')) active fw-bold text-primary border-bottom border-primary border-2 @else text-muted border-0 @endif" href="{{ route('crm.customers.index') }}">
                        <i class="feather-users me-1"></i> Customers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link pb-3 px-3 @if(Route::is('crm.quotations.*')) active fw-bold text-primary border-bottom border-primary border-2 @else text-muted border-0 @endif" href="{{ route('crm.quotations.index') }}">
                        <i class="feather-file-text me-1"></i> Quotes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link pb-3 px-3 @if(Route::is('sales.orders.*')) active fw-bold text-primary border-bottom border-primary border-2 @else text-muted border-0 @endif" href="{{ route('sales.orders.index') }}">
                        <i class="feather-shopping-cart me-1"></i> Sales Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link pb-3 px-3 @if(Route::is('sales.deliveries.*')) active fw-bold text-primary border-bottom border-primary border-2 @else text-muted border-0 @endif" href="{{ route('sales.deliveries.index') }}">
                        <i class="feather-truck me-1"></i> Delivery Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link pb-3 px-3 @if(Route::is('sales.invoices.*')) active fw-bold text-primary border-bottom border-primary border-2 @else text-muted border-0 @endif" href="{{ route('sales.invoices.index') }}">
                        <i class="feather-file me-1"></i> Invoices
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link pb-3 px-3 @if(Route::is('sales.payments.*')) active fw-bold text-primary border-bottom border-primary border-2 @else text-muted border-0 @endif" href="{{ route('sales.payments.index') }}">
                        <i class="feather-dollar-sign me-1"></i> Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link pb-3 px-3 @if(Route::is('sales.returns.*')) active fw-bold text-primary border-bottom border-primary border-2 @else text-muted border-0 @endif" href="{{ route('sales.returns.index') }}">
                        <i class="feather-rotate-ccw me-1"></i> Returns
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
