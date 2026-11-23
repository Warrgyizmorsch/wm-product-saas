@php
    $expiredList = $expiredDocs ?? ($vendor->expiredDocuments());
    $expiringList = $expiringDocs ?? ($vendor->expiringDocuments(30));
@endphp

@if($expiredList->isNotEmpty() || $expiringList->isNotEmpty())
    <div class="alert {{ $expiredList->isNotEmpty() ? 'alert-danger' : 'alert-warning' }} border-start border-4 mb-4 p-3 rounded-3 shadow-sm" role="alert">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="feather-alert-triangle fs-20 {{ $expiredList->isNotEmpty() ? 'text-danger' : 'text-warning' }}"></i>
                <div>
                    <h6 class="fw-bold mb-0 {{ $expiredList->isNotEmpty() ? 'text-danger' : 'text-dark' }}">
                        @if($expiredList->isNotEmpty())
                            Statutory & Compliance Alert: {{ $expiredList->count() }} Expired Document(s) Detected
                        @else
                            Document Expiry Warning: {{ $expiringList->count() }} Document(s) Expiring Soon
                        @endif
                    </h6>
                    <div class="fs-12 text-muted mt-0.5">
                        @if($expiredList->isNotEmpty())
                            <span>Expired: <strong>{{ $expiredList->pluck('document_name')->implode(', ') }}</strong> (Requires immediate renewal/upload).</span>
                        @endif
                        @if($expiringList->isNotEmpty())
                            <span class="ms-1">Expiring within 30 days: <strong>{{ $expiringList->pluck('document_name')->implode(', ') }}</strong>.</span>
                        @endif
                    </div>
                </div>
            </div>
            <div>
                <a href="#documents-pane" onclick="document.getElementById('documents-tab').click();" class="btn btn-sm {{ $expiredList->isNotEmpty() ? 'btn-danger' : 'btn-warning text-dark' }} fw-bold px-3 py-1.5">
                    <i class="feather-file-text me-1"></i>View Documents
                </a>
            </div>
        </div>
    </div>
@endif
