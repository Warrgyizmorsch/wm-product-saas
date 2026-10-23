@php
    $isGoogleConnected = session('google_calendar_connected') 
        || !empty(auth()->user()?->settings['google_token']) 
        || app(\App\Domains\CRM\Services\GoogleCalendarIntegrationService::class)->isAccountConnected(auth()->id());
@endphp

@if(!empty($isGoogleConnected))
    <div class="alert alert-success border-0 bg-success-subtle text-success-emphasis d-flex align-items-center justify-content-between flex-wrap gap-2 rounded-3 py-2 px-3 mb-3 fs-12 fw-medium shadow-2xs">
        <div class="d-flex align-items-center gap-2">
            <i class="feather-check-circle fs-15 text-success"></i> 
            <span><strong>Google Calendar Connected:</strong> Events & Google Meet links automatically sync with live push notifications.</span>
        </div>
        <div class="d-flex align-items-center gap-1">
            <span class="badge bg-success text-white fw-bold px-2 py-1"><i class="feather-zap me-1"></i>Active Sync</span>
            <a href="{{ route('crm.google-calendar.disconnect') }}" class="btn btn-xs btn-outline-danger fw-bold px-2 py-1" onclick="return confirm('Disconnect Google Calendar account?')">
                <i class="feather-log-out me-1"></i>Disconnect
            </a>
        </div>
    </div>
@else
    <div class="alert alert-info border-0 bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-between flex-wrap gap-2 rounded-3 py-2 px-3 mb-3 fs-12 fw-medium shadow-2xs">
        <div class="d-flex align-items-center gap-2">
            <i class="feather-info fs-15 text-info"></i> 
            <span><strong>Google Calendar Integration:</strong> Schedule Google events, calls, and meetings directly into Google Calendar & CRM activities.</span>
        </div>
        <a href="{{ route('crm.google-calendar.connect', ['return_to' => url()->current()]) }}" class="btn btn-xs btn-primary fw-bold px-2.5 py-1 shadow-sm" title="Click to grant Google Calendar & Gmail permissions">
            <i class="feather-external-link me-1"></i> Connect Google Account
        </a>
    </div>
@endif
