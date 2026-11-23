<div class="p-3 border rounded-3 bg-white">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
        <div>
            <h6 class="fw-bold text-dark mb-0 fs-15"><i class="feather-file-check text-warning me-2"></i>Statutory Documents &amp; Compliance Expiry Tracker</h6>
            <span class="text-muted fs-12">MSME certifications, Lower TDS (LDC Form 13), ISO certifications, and vendor contracts</span>
        </div>
        <div>
            <button type="button" class="btn btn-sm btn-primary fw-bold px-3 py-1.5 shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadVendorDocModal">
                <i class="feather-upload me-1"></i>Upload Document
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
                    <th style="width: 25%;">Document Name &amp; Category</th>
                    <th style="width: 18%;">Doc / Reg Number</th>
                    <th style="width: 13%;">Issue Date</th>
                    <th style="width: 16%;">Expiry Date &amp; Validity</th>
                    <th style="width: 12%;" class="text-center">Status</th>
                    <th style="width: 12%;" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendorDocuments as $index => $doc)
                    @php
                        $isExp = $doc->isExpired();
                        $isSoon = $doc->isExpiringSoon(30);
                        $daysLeft = $doc->daysUntilExpiry();
                    @endphp
                    <tr class="{{ $isExp ? 'table-danger' : ($isSoon ? 'table-warning' : '') }}">
                        <td class="text-center text-muted">{{ $index + 1 }}</td>
                        <td>
                            <div class="fw-bold text-dark d-flex align-items-center gap-1.5">
                                <i class="feather-file-text text-primary"></i>
                                {{ $doc->document_name }}
                            </div>
                            <span class="badge bg-light text-muted border fs-10 mt-0.5">
                                {{ \App\Domains\Purchase\Models\VendorDocument::TYPES[$doc->document_type] ?? ucfirst($doc->document_type) }}
                            </span>
                        </td>
                        <td>
                            @if($doc->document_number)
                                <span class="font-monospace text-dark fw-semibold">{{ $doc->document_number }}</span>
                            @else
                                <span class="text-muted fs-11">—</span>
                            @endif
                        </td>
                        <td>
                            {{ $doc->issue_date ? $doc->issue_date->format('d-M-Y') : '—' }}
                        </td>
                        <td>
                            @if($doc->expiry_date)
                                <div class="font-monospace fw-bold {{ $isExp ? 'text-danger' : ($isSoon ? 'text-warning text-dark' : 'text-dark') }}">
                                    {{ $doc->expiry_date->format('d-M-Y') }}
                                </div>
                                <div class="fs-11">
                                    @if($isExp)
                                        <span class="text-danger fw-bold"><i class="feather-alert-octagon me-0.5"></i>Expired {{ abs($daysLeft) }} days ago</span>
                                    @elseif($isSoon)
                                        <span class="text-warning text-dark fw-bold"><i class="feather-alert-circle me-0.5"></i>Expires in {{ $daysLeft }} days</span>
                                    @else
                                        <span class="text-success"><i class="feather-check-circle me-0.5"></i>Valid ({{ $daysLeft }} days left)</span>
                                    @endif
                                </div>
                            @else
                                <span class="badge bg-soft-secondary text-dark fs-11">No Expiry (Perpetual)</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($isExp)
                                <span class="badge bg-danger text-white px-2 py-1 fs-11 fw-bold">EXPIRED</span>
                            @elseif($isSoon)
                                <span class="badge bg-warning text-dark px-2 py-1 fs-11 fw-bold">EXPIRING SOON</span>
                            @else
                                <span class="badge bg-success text-white px-2 py-1 fs-11 fw-bold">ACTIVE / VALID</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex align-items-center justify-content-center gap-1">
                                @if($doc->file_path)
                                    <a href="{{ route('purchase.vendors.documents.download', [$vendor->id, $doc->id]) }}" class="btn btn-sm btn-light border px-2 py-1" title="Download Document" target="_blank">
                                        <i class="feather-download fs-12 text-primary"></i>
                                    </a>
                                @endif
                                <form action="{{ route('purchase.vendors.documents.destroy', [$vendor->id, $doc->id]) }}" method="POST" onsubmit="return confirm('Delete this compliance document?');" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border px-2 py-1" title="Delete Document">
                                        <i class="feather-trash-2 fs-12 text-danger"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="feather-file-text fs-24 d-block mb-1 text-muted"></i>
                            No compliance documents uploaded yet. Click "Upload Document" above to attach MSME, LDC, ISO, or agreement files.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Upload Vendor Document Modal --}}
<div class="modal fade" id="uploadVendorDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('purchase.vendors.documents.store', $vendor->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header border-bottom">
                    <h6 class="modal-title fw-bold text-dark"><i class="feather-upload text-primary me-1.5"></i>Upload Compliance &amp; Statutory Document</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body fs-13">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Document Type <span class="text-danger">*</span></label>
                        <select name="document_type" class="form-select form-select-sm" required>
                            @foreach(\App\Domains\Purchase\Models\VendorDocument::TYPES as $key => $label)
                                <option value="{{ $key }}" {{ $key === 'msme' ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Document Title / Name <span class="text-danger">*</span></label>
                        <input type="text" name="document_name" class="form-control form-control-sm" placeholder="e.g. Udyam MSME Registration Certificate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Certificate / Document / Reference Number</label>
                        <input type="text" name="document_number" class="form-control form-control-sm" placeholder="e.g. UDYAM-MH-12-0012345">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark">Issue Date</label>
                            <input type="date" name="issue_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Attach File (PDF, Image, Doc — Max 10MB)</label>
                        <input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold text-dark">Notes / Remarks</label>
                        <textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>
