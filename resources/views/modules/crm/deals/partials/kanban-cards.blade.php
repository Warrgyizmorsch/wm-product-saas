@foreach($deals as $deal)
    @php
        $dealValue = (float)($deal->kanban_value ?? ($deal->actual_value ?: $deal->estimated_value));
        $hasAcceptedQuote = $deal->has_accepted_quote ? '1' : '0';
        $hasCustomer = !empty($deal->account?->customer_id) ? '1' : '0';
        $isWon = in_array(strtolower((string)$deal->stage), ['won', 'closed won']);
        $isWonOrLost = in_array($deal->stage, ['Won', 'Closed Won', 'Lost', 'Closed Lost']);
    @endphp
    <div class="kanban-card {{ $isWon ? 'kanban-card-locked' : '' }}"
         draggable="{{ $isWon ? 'false' : 'true' }}"
         data-deal-id="{{ $deal->id }}"
         data-deal-title="{{ addslashes($deal->title) }}"
         data-has-accepted-quote="{{ $hasAcceptedQuote }}"
         data-has-customer="{{ $hasCustomer }}"
         data-is-won="{{ $isWon ? '1' : '0' }}"
         data-amount="{{ $dealValue }}"
         @if($isWon) style="cursor: default; border-left: 3.5px solid #22c55e;" @endif>

        <!-- Deal Title & Amount -->
        <div class="d-flex align-items-start justify-content-between mb-1.5">
            <a href="{{ route('crm.deals.show', $deal->id) }}" class="fw-bold text-dark fs-13 hover-underline text-truncate me-2" style="max-width: 180px;" title="{{ $deal->title }}">
                @if($isWon) <i class="feather-lock text-success me-1 fs-11" title="Won - Locked"></i> @endif{{ $deal->title }}
            </a>
            <span class="badge bg-soft-success text-success fs-10 font-monospace fw-bold">
                {{ format_currency($dealValue) }}
            </span>
        </div>

        <!-- Subhead Info -->
        @php
            $kbLead = $deal->lead;
            $kbCompany = $deal->account ? $deal->account->name : ($kbLead ? ($kbLead->company_name ?: $kbLead->contact_person) : null);
            $kbContact = $deal->contact ? $deal->contact->name : ($kbLead ? ($kbLead->contact_person ?: $kbLead->company_name) : null);
        @endphp
        <div class="fs-11 text-muted mb-2">
            <div class="text-truncate mb-0.5">
                <span class="font-monospace text-primary fw-bold">{{ $deal->deal_number }}</span>
            </div>
            @if($kbCompany)
                <div class="text-truncate mb-0.5">
                    <i class="feather-briefcase me-1 text-secondary"></i><strong class="text-dark">{{ $kbCompany }}</strong>
                </div>
            @endif
            @if($kbContact && $kbContact !== $kbCompany)
                <div class="text-truncate"><i class="feather-user me-1 text-secondary"></i>{{ $kbContact }}</div>
            @endif
        </div>

        <!-- Footer Details & Actions -->
        <div class="d-flex align-items-center justify-content-between pt-2 border-top fs-11 text-muted">
            <div>
                @if($deal->closing_date)
                    <i class="feather-calendar me-1 text-secondary"></i>{{ \Illuminate\Support\Carbon::parse($deal->closing_date)->format('d M Y') }}
                @else
                    <span class="text-muted">—</span>
                @endif
            </div>
            <div class="d-flex align-items-center gap-1">
                @if(!$isWonOrLost)
                    <a href="{{ route('crm.deals.edit', $deal->id) }}" class="text-secondary hover-primary px-1" title="{{ __('crm.edit_deal_details') }}">
                        <i class="feather-edit fs-12"></i>
                    </a>
                @endif
                <a href="{{ route('crm.deals.show', $deal->id) }}" class="text-secondary hover-primary px-1" title="{{ __('crm.view_details') }}">
                    <i class="feather-external-link fs-12"></i>
                </a>
            </div>
        </div>
    </div>
@endforeach
