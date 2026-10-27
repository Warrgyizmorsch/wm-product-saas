@php
    $tenantObj = tenant() ?? \App\Models\Tenant::find(tenant_id() ?? (auth()->user()?->tenant_id ?? 1));
    $settings = is_array($tenantObj?->settings) ? $tenantObj->settings : [];
    $isGeoVerificationEnabled = !empty($settings['field_visit_verification']);
    $mapplsApiKey = trim((string)($settings['mappls_api_key'] ?? ''));
    $prefix = $idPrefix ?? 'meeting_' . uniqid();
    $addrName = $addressFieldName ?? 'location_address';
    $latName = $latFieldName ?? 'latitude';
    $lngName = $lngFieldName ?? 'longitude';
    $initialDisplay = (!empty($initialShow) && $isGeoVerificationEnabled) ? 'block' : 'none';
@endphp

<div id="container_meeting_location_{{ $prefix }}" class="crm-meeting-location-container mb-3" style="display: {{ $initialDisplay }};">
    <div class="p-3 bg-light rounded-3 border">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="form-label fw-bold text-dark fs-12 mb-0 d-flex align-items-center gap-1.5">
                <i class="feather-map-pin text-primary fs-13"></i>
                <span>{{ $label ?? 'Meeting Venue Location' }}</span>
            </label>
            <span class="badge bg-soft-primary text-primary fs-10 fw-semibold px-2 py-0.5 rounded-pill">
                <i class="feather-shield fs-9 me-1"></i>Geo-Verification
            </span>
        </div>

        <p class="text-muted fs-11 mb-2">
            Enter the meeting venue / office complex in India (powered by Mappls MapmyIndia) or pick the pin on the map.
        </p>

        <!-- Address Search Input Group -->
        <div class="input-group input-group-sm mb-1 shadow-2xs">
            <span class="input-group-text bg-white border-end-0 text-muted">
                <i class="feather-search fs-12 text-primary"></i>
            </span>
            <input type="text"
                   name="{{ $addrName }}"
                   id="loc_address_{{ $prefix }}"
                   class="form-control form-control-sm border-start-0 fs-12"
                   placeholder="Enter landmark / airport / office address..."
                   value="{{ $currentAddress ?? '' }}"
                   autocomplete="off">
            <button type="button"
                    class="btn btn-primary fs-11 px-2.5 d-flex align-items-center gap-1"
                    id="btn_search_address_{{ $prefix }}"
                    title="Search location and pinpoint on map">
                <i class="feather-search fs-11"></i>
                <span>Search</span>
            </button>
            <button type="button"
                    class="btn btn-outline-secondary fs-11 px-2.5 d-flex align-items-center gap-1"
                    id="btn_detect_gps_{{ $prefix }}"
                    title="Detect your current device GPS location">
                <i class="feather-crosshair fs-12 text-danger"></i>
                <span class="d-none d-sm-inline">Current GPS</span>
            </button>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-2 px-0.5">
            <span class="text-muted fs-10">
                <i class="feather-info text-primary me-0.5"></i><strong>Tip:</strong> Address daal kar <strong>Search</strong> par click karein ya <strong>Enter</strong> dabayein, map automatically us jagah pin drop kar dega.
            </span>
        </div>

        <!-- Coordinates Hidden Inputs -->
        <input type="hidden" name="{{ $latName }}" id="loc_lat_{{ $prefix }}" value="{{ $currentLat ?? '' }}">
        <input type="hidden" name="{{ $lngName }}" id="loc_lng_{{ $prefix }}" value="{{ $currentLng ?? '' }}">

        <!-- Map Container -->
        <div id="meeting_map_{{ $prefix }}"
             class="w-100 rounded-2 border shadow-2xs overflow-hidden mb-2"
             style="height: 200px; background-color: #e2e8f0; position: relative;">
            @if(empty($mapplsApiKey))
                <div class="d-flex flex-column align-items-center justify-content-center h-100 p-3 text-center bg-light">
                    <div class="avatar-text avatar-sm bg-soft-warning text-warning rounded-circle mb-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="feather-map-pin fs-14"></i>
                    </div>
                    <span class="fw-bold fs-12 text-dark">Mappls Interactive Map</span>
                    <p class="text-muted fs-11 mb-2">
                        Enter your free Mappls API key in CRM Settings to render India's high-accuracy vector map & POI search.
                    </p>
                    <a href="{{ route('crm.settings.index', ['tab' => 'field-visit']) }}" target="_blank" class="btn btn-xs btn-outline-primary fw-semibold">
                        <i class="feather-settings me-1"></i>Configure Free Mappls Key
                    </a>
                </div>
            @else
                <div class="d-flex align-items-center justify-content-center h-100 text-muted fs-11">
                    <span class="spinner-border spinner-border-sm text-primary me-2"></span> Loading Mappls Vector Map...
                </div>
            @endif
        </div>

        <!-- Status Display Bar -->
        <div class="d-flex align-items-center justify-content-between px-2 py-1 bg-white rounded-2 border fs-11 text-muted">
            <div class="d-flex align-items-center gap-1">
                <i class="feather-navigation fs-10 text-primary"></i>
                <span id="coords_display_{{ $prefix }}">
                    @if(!empty($currentLat) && !empty($currentLng))
                        Lat: {{ round($currentLat, 5) }}, Lng: {{ round($currentLng, 5) }}
                    @else
                        No pin selected (click map or search address)
                    @endif
                </span>
            </div>
            <span class="badge bg-light text-secondary border fs-10">Mappls MapmyIndia</span>
        </div>
    </div>
</div>

<script>
    (function() {
        function runInit() {
            if (window.initCrmMapplsPicker) {
                window.initCrmMapplsPicker(
                    '{{ $prefix }}',
                    '{{ $mapplsApiKey }}',
                    {{ !empty($currentLat) ? $currentLat : 'null' }},
                    {{ !empty($currentLng) ? $currentLng : 'null' }}
                );
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', runInit);
        } else {
            runInit();
        }
    })();
</script>
