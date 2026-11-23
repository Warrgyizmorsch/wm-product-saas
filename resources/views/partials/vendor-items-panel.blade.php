<div class="p-3 border rounded-3 bg-white">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h6 class="fw-bold text-dark mb-0 fs-15"><i class="feather-package text-success me-2"></i>Supplied Items Catalog &amp; Contracted Pricing</h6>
            <span class="text-muted fs-12">Vendor-specific catalog codes, contracted purchase rates, MOQ, and lead times</span>
        </div>
        <div>
            <button type="button" class="btn btn-sm btn-primary fw-bold px-3 py-1.5 shadow-sm" data-bs-toggle="modal" data-bs-target="#addVendorItemModal">
                <i class="feather-plus me-1"></i>Map Supplied Item
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2 px-3 fs-13 mb-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2 px-3 fs-13 mb-3">{{ session('error') }}</div>
    @endif

    <div class="table-responsive">
        <table class="table table-hover table-striped table-bordered align-middle mb-0 fs-13">
            <thead class="table-light text-muted text-uppercase fs-11">
                <tr>
                    <th style="width: 4%;">#</th>
                    <th style="width: 24%;">Internal Product (SKU)</th>
                    <th style="width: 22%;">Vendor Item Code &amp; Name</th>
                    <th style="width: 14%;" class="text-end">Contracted Rate</th>
                    <th style="width: 10%;" class="text-center">MOQ</th>
                    <th style="width: 12%;" class="text-center">Lead Time</th>
                    <th style="width: 14%;" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendorItems as $index => $item)
                    <tr>
                        <td class="text-center text-muted">{{ $index + 1 }}</td>
                        <td>
                            <div class="fw-bold text-dark">{{ $item->product?->name ?? 'N/A' }}</div>
                            <span class="badge bg-soft-primary text-primary font-monospace fs-11">SKU: {{ $item->product?->sku ?? '—' }}</span>
                            @if($item->is_preferred)
                                <span class="badge bg-soft-success text-success fs-10 ms-1">Preferred</span>
                            @endif
                        </td>
                        <td>
                            @if($item->vendor_item_code)
                                <div class="font-monospace text-dark fw-bold fs-12">{{ $item->vendor_item_code }}</div>
                            @else
                                <span class="text-muted fs-11">No vendor code set</span>
                            @endif
                            @if($item->vendor_item_name)
                                <div class="text-muted fs-11 mt-0.5">{{ $item->vendor_item_name }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($item->unit_price !== null)
                                <div class="fw-bold font-monospace text-dark">{!! format_currency($item->unit_price) !!}</div>
                                <button type="button" class="btn btn-link btn-sm text-primary p-0 fs-11 text-decoration-none" onclick="openPriceHistoryModal({{ $item->id }}, '{{ addslashes($item->product?->name ?? '') }}')">
                                    <i class="feather-clock me-1"></i>Price Log
                                </button>
                            @else
                                <span class="text-muted fs-11">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-soft-info text-dark font-monospace fs-12 px-2 py-1">
                                {{ number_format((float)$item->moq, 2) }}
                            </span>
                        </td>
                        <td class="text-center">
                            @if($item->lead_time_days > 0)
                                <span class="badge bg-light text-dark border font-monospace fs-12 px-2 py-1">
                                    <i class="feather-clock me-1 text-primary"></i>{{ $item->lead_time_days }} Days
                                </span>
                            @else
                                <span class="text-muted fs-11">Immediate / 0d</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex align-items-center justify-content-center gap-1">
                                <button type="button" class="btn btn-sm btn-light border px-2 py-1" data-bs-toggle="modal" data-bs-target="#editVendorItemModal{{ $item->id }}" title="Edit Item Details">
                                    <i class="feather-edit-2 fs-12 text-primary"></i>
                                </button>
                                <form action="{{ route('purchase.vendors.items.destroy', [$vendor->id, $item->id]) }}" method="POST" onsubmit="return confirm('Remove this product from vendor catalogue?');" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border px-2 py-1" title="Delete Mapping">
                                        <i class="feather-trash-2 fs-12 text-danger"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>

                    {{-- Edit Modal for each Item --}}
                    <div class="modal fade" id="editVendorItemModal{{ $item->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form action="{{ route('purchase.vendors.items.update', [$vendor->id, $item->id]) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header border-bottom">
                                        <h6 class="modal-title fw-bold text-dark">
                                            <i class="feather-edit-2 text-primary me-1.5"></i>Edit Supplied Item: {{ $item->product?->name }}
                                        </h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body fs-13">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-dark">Vendor Item Code (Part Number)</label>
                                            <input type="text" name="vendor_item_code" class="form-control form-control-sm" value="{{ $item->vendor_item_code }}" placeholder="e.g. CP-710-18">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-dark">Vendor Item Name / Description</label>
                                            <input type="text" name="vendor_item_name" class="form-control form-control-sm" value="{{ $item->vendor_item_name }}" placeholder="e.g. Century Club Prime 18mm">
                                        </div>
                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <label class="form-label fw-semibold text-dark">Contracted Rate ({{ active_currency_symbol() }})</label>
                                                <input type="number" step="0.01" name="unit_price" class="form-control form-control-sm" value="{{ $item->unit_price }}" placeholder="0.00">
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label fw-semibold text-dark">Effective From Date</label>
                                                <input type="date" name="effective_from" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold text-dark">Price Change Reason (Logged in Price Log)</label>
                                            <input type="text" name="price_change_reason" class="form-control form-control-sm" placeholder="e.g. Quarterly price revision / Raw material hike">
                                        </div>
                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <label class="form-label fw-semibold text-dark">Vendor MOQ (Min Order Qty)</label>
                                                <input type="number" step="0.01" name="moq" class="form-control form-control-sm" value="{{ $item->moq }}" min="0.01" required>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label fw-semibold text-dark">Lead Time (In Days)</label>
                                                <input type="number" name="lead_time_days" class="form-control form-control-sm" value="{{ $item->lead_time_days }}" min="0" required>
                                            </div>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="is_preferred" value="1" id="pref{{ $item->id }}" {{ $item->is_preferred ? 'checked' : '' }}>
                                            <label class="form-check-label fw-semibold text-dark" for="pref{{ $item->id }}">
                                                Mark as Preferred Supplier for this item
                                            </label>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-top py-2">
                                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="feather-package fs-24 d-block mb-1 text-muted"></i>
                            No products mapped to this vendor yet. Click "Map Supplied Item" above to add products.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add Vendor Item Modal --}}
<div class="modal fade" id="addVendorItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('purchase.vendors.items.store', $vendor->id) }}" method="POST">
                @csrf
                <div class="modal-header border-bottom">
                    <h6 class="modal-title fw-bold text-dark"><i class="feather-plus-circle text-primary me-1.5"></i>Map Product to Vendor Catalog</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body fs-13">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Select Internal Product <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select form-select-sm" required>
                            <option value="">-- Choose Product / SKU --</option>
                            @foreach($allProducts as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} (SKU: {{ $p->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Vendor Item Code (Vendor Part Number)</label>
                        <input type="text" name="vendor_item_code" class="form-control form-control-sm" placeholder="e.g. CP-CLUB-710-18">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Vendor Item Name / Description</label>
                        <input type="text" name="vendor_item_name" class="form-control form-control-sm" placeholder="e.g. Century Club Prime 18mm BWP">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark">Contracted Unit Rate ({{ active_currency_symbol() }})</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control form-control-sm" placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark">Vendor MOQ (Min Order Qty)</label>
                            <input type="number" step="0.01" name="moq" class="form-control form-control-sm" value="1.00" min="0.01" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Procurement Lead Time (In Days)</label>
                        <input type="number" name="lead_time_days" class="form-control form-control-sm" value="0" min="0" placeholder="e.g. 7" required>
                        <span class="text-muted fs-11">Days required between PO placement and delivery gate-in.</span>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_preferred" value="1" id="prefNew">
                        <label class="form-check-label fw-semibold text-dark" for="prefNew">
                            Set as Preferred Supplier for this product
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">Save Mapping</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Price History Modal --}}
<div class="modal fade" id="priceHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold text-dark"><i class="feather-clock text-primary me-1.5"></i>Rate History &amp; Price Hike Log: <span id="phItemName" class="text-primary"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 fs-13">
                <div id="phLoading" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-1" role="status"></div> Loading price logs...
                </div>
                <table class="table table-striped table-hover align-middle mb-0" id="phTable" style="display: none;">
                    <thead class="table-light fs-11 text-uppercase text-muted">
                        <tr>
                            <th>Effective Date</th>
                            <th class="text-end">Old Price</th>
                            <th class="text-end">New Price</th>
                            <th>Reason / Revision Log</th>
                            <th>Approved By</th>
                        </tr>
                    </thead>
                    <tbody id="phTableBody"></tbody>
                </table>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openPriceHistoryModal(itemId, itemName) {
    document.getElementById('phItemName').textContent = itemName;
    document.getElementById('phLoading').style.display = 'block';
    document.getElementById('phTable').style.display = 'none';
    const tbody = document.getElementById('phTableBody');
    tbody.innerHTML = '';

    const modal = new bootstrap.Modal(document.getElementById('priceHistoryModal'));
    modal.show();

    fetch('{{ url("purchase/vendors/{$vendor->id}/items") }}/' + itemId + '/price-history')
        .then(res => res.json())
        .then(data => {
            document.getElementById('phLoading').style.display = 'none';
            document.getElementById('phTable').style.display = 'table';
            if (data.history && data.history.length > 0) {
                // Reason and approver are user-entered text: write them as text, never as HTML.
                const cell = (text, className) => {
                    const td = document.createElement('td');
                    td.className = className;
                    td.textContent = text;
                    return td;
                };
                data.history.forEach(row => {
                    const tr = document.createElement('tr');
                    tr.appendChild(cell(row.effective_from || '—', 'font-monospace fw-bold'));
                    tr.appendChild(cell(row.old_price !== null ? row.old_price : '—', 'text-end font-monospace text-muted'));
                    tr.appendChild(cell(row.new_price, 'text-end font-monospace fw-bold text-success'));
                    tr.appendChild(cell(row.reason, 'text-dark'));
                    tr.appendChild(cell(row.approved_by, 'text-muted fs-12'));
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">No price change history recorded for this item yet.</td></tr>';
            }
        })
        .catch(err => {
            document.getElementById('phLoading').innerHTML = '<span class="text-danger">Failed to load price history.</span>';
        });
}
</script>
