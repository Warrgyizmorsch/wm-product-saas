{{--
    Second-person verification panel for a customer or vendor (FRS 2.3, C-15).
    Expects: $party (Customer|Vendor), $type ('customer'|'vendor').
--}}
@php
    $verification = app(\App\Services\PartyVerificationService::class);
    $isCustomer = $type === 'customer';
    $verifyRoute = $isCustomer ? 'crm.customers.verify' : 'purchase.vendors.verify';
    $returnRoute = $isCustomer ? 'crm.customers.verification.return' : 'purchase.vendors.verification.return';
    $resubmitRoute = $isCustomer ? 'crm.customers.verification.resubmit' : 'purchase.vendors.verification.resubmit';
    $user = auth()->user();
    $canVerify = $verification->canVerify($user, $party);
    $isMaker = $verification->isMaker($user, $party);
    $required = $verification->requiredChecks($party);
    $labels = ['gstin' => 'GSTIN', 'pan' => 'PAN', 'bank' => 'Bank details'];
    $logs = $party->verificationLogs()->with('actor')->limit(8)->get();
@endphp

@if($verification->enabled())
    @if($party->isVerified())
        <div class="d-flex align-items-center gap-2 mb-3 fs-12 text-muted">
            <span class="badge bg-soft-success text-success">Verified</span>
            @if($party->verified_by)
                <span>by {{ $party->verifier?->name ?? ('user #' . $party->verified_by) }}
                    @if($party->verified_at) on {{ $party->verified_at->format('d M Y H:i') }} @endif</span>
            @else
                <span>{{ $party->verification_remarks }}</span>
            @endif
        </div>
    @else
        <div class="border rounded-3 p-3 mb-4 {{ $party->isReturnedForCorrection() ? 'border-danger' : 'border-warning' }}">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div>
                    <span class="badge {{ $party->isReturnedForCorrection() ? 'bg-soft-danger text-danger' : 'bg-soft-warning text-warning' }}">
                        {{ $party->verificationLabel() }}
                    </span>
                    <span class="fs-13 fw-semibold ms-2">This {{ $type }} is inactive until a second person verifies it.</span>
                </div>
                <span class="fs-12 text-muted">
                    Submitted by {{ $party->submitter?->name ?? ($party->submitted_by ? 'user #' . $party->submitted_by : 'unknown') }}
                    @if($party->submitted_at) on {{ $party->submitted_at->format('d M Y H:i') }} @endif
                </span>
            </div>

            @if($party->isReturnedForCorrection() && $party->verification_remarks)
                <div class="alert alert-danger py-2 fs-12 mb-3">Returned: {{ $party->verification_remarks }}</div>
            @endif

            <div class="row g-2 fs-13 mb-3">
                <div class="col-md-4"><span class="text-muted">GSTIN:</span> <strong>{{ $party->gstin ?: '-' }}</strong></div>
                <div class="col-md-4"><span class="text-muted">PAN:</span> <strong>{{ $party->pan ?: '-' }}</strong></div>
                <div class="col-md-4"><span class="text-muted">Bank:</span>
                    <strong>{{ $party->bank_name ?: '-' }}</strong>
                    @if($party->account_number) / A/c {{ $party->account_number }} @endif
                    @if($party->ifsc_code) / {{ $party->ifsc_code }} @endif
                </div>
            </div>

            @if($party->isReturnedForCorrection())
                <p class="fs-12 text-muted mb-2">Correct the details by editing this {{ $type }}; saving sends it back to the verifier.</p>
                <form method="POST" action="{{ route($resubmitRoute, $party->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">Resubmit without changes</button>
                </form>
            @elseif($canVerify)
                @if($isMaker)
                    <div class="alert alert-warning py-2 fs-12 mb-0">
                        You created or last edited these details, so a different authorised person must verify them.
                    </div>
                @else
                    <form method="POST" action="{{ route($verifyRoute, $party->id) }}" class="mb-2">
                        @csrf
                        @forelse($required as $key)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="checks[{{ $key }}]" value="1" id="chk-{{ $key }}-{{ $party->id }}">
                                <label class="form-check-label fs-13" for="chk-{{ $key }}-{{ $party->id }}">
                                    I checked the {{ $labels[$key] }} against the supporting document
                                </label>
                            </div>
                        @empty
                            <p class="fs-12 text-muted mb-2">No GSTIN, PAN or bank details were entered, so there is nothing to check.</p>
                        @endforelse
                        <input type="text" name="remarks" class="form-control form-control-sm mt-2" maxlength="1000" placeholder="Remarks (optional)">
                        <button type="submit" class="btn btn-sm btn-success mt-2">Verify and activate</button>
                    </form>

                    <form method="POST" action="{{ route($returnRoute, $party->id) }}" class="d-flex gap-2">
                        @csrf
                        <input type="text" name="reason" class="form-control form-control-sm" maxlength="1000" required placeholder="Reason for returning">
                        <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">Return for correction</button>
                    </form>
                @endif
            @else
                <p class="fs-12 text-muted mb-0">Waiting for an authorised verifier.</p>
            @endif
        </div>
    @endif

    @if($logs->isNotEmpty())
        <details class="mb-3 fs-12">
            <summary class="text-muted">Verification history</summary>
            <ul class="list-unstyled mt-2 mb-0">
                @foreach($logs as $log)
                    <li class="py-1 border-bottom">
                        <strong>{{ str_replace('_', ' ', ucfirst($log->action)) }}</strong>
                        by {{ $log->actor?->name ?? 'system' }}
                        <span class="text-muted">{{ $log->created_at?->format('d M Y H:i') }}</span>
                        @if($log->remarks) <span class="text-muted">: {{ $log->remarks }}</span> @endif
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
@endif
