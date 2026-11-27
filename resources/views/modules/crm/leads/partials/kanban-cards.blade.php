@foreach($leads as $lead)
    @php
        $expAmt = (float)($lead->expected_amount ?: ($lead->quotations->last()?->total_amount ?: 0));
        $kStarsMap = ['Low' => 1, 'Medium' => 2, 'High' => 3, 'Urgent' => 4];
        $kStarsCount = $kStarsMap[$lead->priority] ?? 0;
        $kBadgeClass = match($lead->priority) {
            'Low'    => 'bg-soft-success text-success',
            'Medium' => 'bg-soft-warning text-warning',
            'High'   => 'bg-soft-danger text-danger',
            'Urgent' => 'bg-danger text-white',
            default  => 'bg-soft-secondary text-secondary',
        };
        $kStarLabels = [1 => 'Low', 2 => 'Medium', 3 => 'High', 4 => 'Urgent'];
        $isDealing = strcasecmp($lead->status, 'Dealing') === 0;
        $isWon = strcasecmp($lead->status, 'Won') === 0 || $lead->is_customer;
        $isLocked = $isDealing || $isWon;
    @endphp
    <div class="kanban-card {{ $isLocked ? 'kanban-card-locked' : '' }}" draggable="{{ $isLocked ? 'false' : 'true' }}" data-lead-id="{{ $lead->id }}" data-company-name="{{ addslashes($lead->company_name) }}" data-status="{{ $lead->status ?: 'New' }}" data-has-account-deal="{{ (($lead->crm_account_id && $lead->crm_deal_id) || $lead->is_customer || $isWon) ? '1' : '0' }}" data-is-qualified="{{ $lead->is_qualified ? '1' : '0' }}" data-amount="{{ $expAmt }}" style="{{ $isLocked ? 'cursor: default;' : '' }}">
        <!-- Lead Title & Value -->
        <div class="d-flex align-items-start justify-content-between mb-1.5">
            <a href="{{ route('crm.leads.show', $lead->id) }}" class="fw-bold text-dark fs-13 hover-underline text-truncate me-2" style="max-width: 170px;" title="{{ $lead->company_name }}">
                {{ $lead->company_name }}
            </a>
            <div class="d-flex align-items-center gap-1">
                @if($isLocked)
                    <span class="badge bg-soft-secondary text-secondary fs-10 px-1.5 py-0.5 lock-badge" title="{{ $isWon ? 'Won Stage Locked' : 'Dealing Stage Locked' }}">
                        <i class="feather-lock me-1"></i>Locked
                    </span>
                @endif
                <span class="badge bg-soft-success text-success fs-10 font-monospace fw-bold">
                    {{ format_currency($expAmt) }}
                </span>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="fs-11 text-muted mb-2">
            <div class="text-truncate mb-0.5"><i class="feather-user me-1 text-secondary"></i>{{ $lead->contact_name ?: 'No Contact Name' }}</div>
            @if($lead->phone)
                <div><i class="feather-phone me-1 text-secondary"></i><a href="tel:{{ $lead->phone }}" class="text-muted">{{ $lead->phone }}</a></div>
            @endif
        </div>

        <!-- Priority Stars & Actions Footer -->
        <div class="d-flex align-items-center justify-content-between pt-2 border-top">
            <!-- Star Rating Widget -->
            <div class="d-flex align-items-center gap-1">
                <div class="star-rating-widget d-inline-flex align-items-center gap-1" id="starRating_{{ $lead->id }}" data-current-stars="{{ $kStarsCount }}" data-current-priority="{{ $lead->priority }}">
                    @for($i = 1; $i <= 4; $i++)
                        @php $targetPriority = $kStarLabels[$i]; @endphp
                        <i class="feather-star star-icon {{ $i <= $kStarsCount ? 'active-star' : 'inactive-star' }}"
                           data-star="{{ $i }}"
                           data-priority="{{ $targetPriority }}"
                           data-lead-id="{{ $lead->id }}"
                           data-bs-toggle="tooltip"
                           data-bs-placement="top"
                           title="{{ $targetPriority }} ({{ $i }}★)"
                           onclick="updateLeadPriority({{ $lead->id }}, '{{ $targetPriority }}', this)"></i>
                    @endfor
                </div>
                <span class="badge fs-10 ms-1 priority-badge-{{ $lead->id }} {{ $kBadgeClass }}">
                    {{ $lead->priority ?: 'Unset' }}
                </span>
            </div>

            <div class="d-flex align-items-center gap-1">
                <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_lead' => 1]) }}" class="btn btn-xs btn-link text-muted p-0 border-0 me-1" title="Edit Lead">
                    <i class="feather-edit fs-12"></i>
                </a>
                <a href="{{ route('crm.leads.show', $lead->id) }}" class="btn btn-xs btn-outline-primary py-0 px-1.5 fs-10 fw-semibold">
                    View
                </a>
            </div>
        </div>
    </div>
@endforeach
