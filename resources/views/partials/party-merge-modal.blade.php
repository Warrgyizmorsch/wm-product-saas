{{--
    Party Merge Modal (FRS 2.3 / Task C-18).
    Supports Customer and Vendor record consolidation into a surviving target record.
    Expects: $party, $type ('customer'|'vendor').
--}}
@php
    $isCustomer = ($type === 'customer');
    $partyTitle = $isCustomer ? 'Customer' : 'Vendor';
    $previewUrl = $isCustomer ? route('crm.customers.merge.preview', $party->id) : route('purchase.vendors.merge.preview', $party->id);
    $actionUrl = $isCustomer ? route('crm.customers.merge.store', $party->id) : route('purchase.vendors.merge.store', $party->id);
    $tenantId = $party->tenant_id ?? (tenant_id() ?? 1);

    // List potential target parties in the same tenant excluding this party
    $targetCandidates = $isCustomer
        ? \App\Domains\CRM\Models\Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', '!=', $party->id)
            ->whereNull('merged_into_id')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'gstin', 'status'])
        : \App\Domains\Inventory\Models\Vendor::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('id', '!=', $party->id)
            ->whereNull('merged_into_id')
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'gstin', 'status']);
@endphp

<div class="modal fade" id="mergePartyModal" tabindex="-1" aria-labelledby="mergePartyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="{{ $actionUrl }}" method="POST" id="partyMergeForm">
                @csrf
                <div class="modal-header bg-light border-bottom py-3">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center" id="mergePartyModalLabel">
                        <i class="feather-git-merge text-warning me-2 fs-20"></i>
                        Merge Duplicate {{ $partyTitle }} Record (C-18)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="alert alert-warning d-flex align-items-start gap-2 py-2.5 px-3 fs-13 mb-3">
                        <i class="feather-alert-triangle fs-16 mt-0.5 flex-shrink-0 text-warning"></i>
                        <div>
                            <strong>Irreversible Master Consolidation:</strong>
                            All invoices, orders, quotations, payments, branches, and ledger entries belonging to
                            <strong>{{ $party->name }} (#{{ $party->id }})</strong> will be moved into the selected Target record.
                            This record will then be permanently archived as a duplicate. <em>(Note: There is no automated unmerge; consolidation is permanent.)</em>
                        </div>
                    </div>

                    {{-- Step 1: Select Target --}}
                    <div class="mb-3">
                        <label for="target_party_id" class="form-label fw-bold text-dark fs-13">
                            Select Surviving (Target) {{ $partyTitle }}:
                            <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="target_party_id" name="target_id" required>
                            <option value="">-- Choose the master {{ strtolower($partyTitle) }} record to keep --</option>
                            @foreach($targetCandidates as $cand)
                                <option value="{{ $cand->id }}" data-name="{{ $cand->name }}" data-gstin="{{ $cand->gstin }}">
                                    #{{ $cand->id }} — {{ $cand->name }}
                                    @if($cand->gstin) [GSTIN: {{ $cand->gstin }}] @endif
                                    @if(strtolower((string)$cand->status) !== 'active') ({{ ucfirst($cand->status) }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text fs-12 text-muted">
                            Tip: Pick the original, verified legal entity (e.g. Head Office) that will absorb this duplicate.
                        </div>
                    </div>

                    {{-- Step 2: Live Impact Preview --}}
                    <div id="mergePreviewContainer" class="d-none border rounded-3 p-3 bg-light mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                            <span class="fw-bold text-dark fs-13">
                                <i class="feather-eye me-1.5 text-primary"></i>Impact Preview: Records to be consolidated
                            </span>
                            <span class="badge bg-soft-info text-info fs-11" id="previewTargetBadge">Target: #...</span>
                        </div>

                        <div id="panMismatchWarning" class="alert alert-danger d-none d-flex align-items-start gap-2 py-2 px-3 fs-12 mb-2">
                            <i class="feather-alert-octagon fs-15 mt-0.5 text-danger flex-shrink-0"></i>
                            <div id="panMismatchWarningText"></div>
                        </div>

                        <div class="row g-2 text-center fs-12 mb-2" id="previewStatsRow">
                            {{-- Dynamically populated via JS --}}
                        </div>

                        <div class="small text-muted border-top pt-2 mt-2">
                            <i class="feather-info me-1 text-primary"></i>
                            <strong>Multi-Branch Preservation (C-17):</strong> Any branches or delivery sites on this record will be adopted by the target as active branches with GSTIN and addresses intact.
                        </div>
                    </div>

                    {{-- Step 3: Confirmation reason and authorization checkbox --}}
                    <div class="mb-3">
                        <label for="merge_reason" class="form-label text-muted fs-13">Merge Note / Reason (Optional):</label>
                        <input type="text" class="form-control form-control-sm" id="merge_reason" name="reason"
                            placeholder="e.g. Migrated duplicate from old Excel files; consolidated under Head Office.">
                    </div>

                    <div class="form-check p-2.5 bg-soft-danger rounded-2 border border-danger-subtle">
                        <input class="form-check-input ms-1 me-2" type="checkbox" name="confirm_merge" value="1" id="confirm_merge_checkbox" required>
                        <label class="form-check-label fs-13 text-dark fw-medium" for="confirm_merge_checkbox">
                            I verify that <strong>{{ $party->name }} (#{{ $party->id }})</strong> is a duplicate and authorize safely consolidating its entire history into the chosen target record.
                        </label>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top py-2.5">
                    <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning px-4 fw-bold" id="executeMergeBtn" disabled>
                        <i class="feather-git-merge me-1.5"></i>Execute Merge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const targetSelect = document.getElementById('target_party_id');
    const previewContainer = document.getElementById('mergePreviewContainer');
    const previewStatsRow = document.getElementById('previewStatsRow');
    const previewTargetBadge = document.getElementById('previewTargetBadge');
    const panMismatchWarning = document.getElementById('panMismatchWarning');
    const panMismatchWarningText = document.getElementById('panMismatchWarningText');
    const confirmCheckbox = document.getElementById('confirm_merge_checkbox');
    const executeBtn = document.getElementById('executeMergeBtn');
    const previewBaseUrl = @json($previewUrl);

    function checkSubmitState() {
        if (targetSelect && confirmCheckbox && executeBtn) {
            executeBtn.disabled = !(targetSelect.value && confirmCheckbox.checked);
        }
    }

    if (confirmCheckbox) {
        confirmCheckbox.addEventListener('change', checkSubmitState);
    }

    if (targetSelect) {
        targetSelect.addEventListener('change', function () {
            const targetId = this.value;
            checkSubmitState();

            if (!targetId) {
                previewContainer.classList.add('d-none');
                return;
            }

            const targetName = this.options[this.selectedIndex].getAttribute('data-name');
            previewTargetBadge.textContent = 'Target: #' + targetId + ' (' + targetName + ')';

            // Fetch live preview from backend
            fetch(previewBaseUrl + '?target_id=' + encodeURIComponent(targetId), {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.preview) {
                    const p = data.preview;
                    const doc = p.documents || {};
                    let statsHtml = '';

                    if (p.party_type === 'customer') {
                        statsHtml = `
                            <div class="col-4">
                                <div class="p-2 border rounded bg-white">
                                    <div class="fw-bold fs-15 text-dark">${doc.invoices_count || 0}</div>
                                    <div class="text-muted">Invoices</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 border rounded bg-white">
                                    <div class="fw-bold fs-15 text-dark">${doc.sales_orders_count || 0}</div>
                                    <div class="text-muted">Orders</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 border rounded bg-white">
                                    <div class="fw-bold fs-15 text-dark">${doc.payments_count || 0}</div>
                                    <div class="text-muted">Payments</div>
                                </div>
                            </div>
                            <div class="col-4 mt-2">
                                <div class="p-2 border rounded bg-white">
                                    <div class="fw-bold fs-15 text-dark">${p.branches_count || 0}</div>
                                    <div class="text-muted">Branches</div>
                                </div>
                            </div>
                            <div class="col-4 mt-2">
                                <div class="p-2 border rounded bg-white">
                                    <div class="fw-bold fs-15 text-dark">${p.contacts_count || 0}</div>
                                    <div class="text-muted">Contacts</div>
                                </div>
                            </div>
                            <div class="col-4 mt-2">
                                <div class="p-2 border rounded bg-white">
                                    <div class="fw-bold fs-15 text-dark">${p.accounting_entries_count || 0}</div>
                                    <div class="text-muted">Ledger Lines</div>
                                </div>
                            </div>
                        `;
                    } else {
                        statsHtml = `
                            <div class="col-4">
                                <div class="p-2 border rounded bg-white">
                                    <div class="fw-bold fs-15 text-dark">${doc.bills_count || 0}</div>
                                    <div class="text-muted">Bills</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 border rounded bg-white">
                                    <div class="fw-bold fs-15 text-dark">${doc.purchase_orders_count || 0}</div>
                                    <div class="text-muted">PO Orders</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 border rounded bg-white">
                                    <div class="fw-bold fs-15 text-dark">${doc.payments_count || 0}</div>
                                    <div class="text-muted">Payments</div>
                                </div>
                            </div>
                            <div class="col-4 mt-2">
                                <div class="p-2 border rounded bg-white">
                                    <div class="fw-bold fs-15 text-dark">${p.branches_count || 0}</div>
                                    <div class="text-muted">Branches</div>
                                </div>
                            </div>
                            <div class="col-4 mt-2">
                                <div class="p-2 border rounded bg-white">
                                    <div class="fw-bold fs-15 text-dark">${p.contacts_count || 0}</div>
                                    <div class="text-muted">Contacts</div>
                                </div>
                            </div>
                            <div class="col-4 mt-2">
                                <div class="p-2 border rounded bg-white">
                                    <div class="fw-bold fs-15 text-dark">${p.accounting_entries_count || 0}</div>
                                    <div class="text-muted">Ledger Lines</div>
                                </div>
                            </div>
                        `;
                    }

                    previewStatsRow.innerHTML = statsHtml;

                    if (panMismatchWarning && panMismatchWarningText) {
                        if (p.legal_entity_check && p.legal_entity_check.pan_mismatch) {
                            panMismatchWarningText.innerHTML = '<strong>Caution — Legal Entity Mismatch:</strong> ' +
                                (p.legal_entity_check.warning || 'Target party has a different PAN/GSTIN.');
                            panMismatchWarning.classList.remove('d-none');
                        } else {
                            panMismatchWarning.classList.add('d-none');
                        }
                    }

                    previewContainer.classList.remove('d-none');
                }
            })
            .catch(err => {
                console.error('Failed to load merge preview:', err);
            });
        });
    }
});
</script>
