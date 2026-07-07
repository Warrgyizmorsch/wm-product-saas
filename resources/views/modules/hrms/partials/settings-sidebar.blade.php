@php
    $isSalary = request()->routeIs('hrms.salary-structure.index') || request()->query('tab') === 'salary-structure';
    $isLeave = request()->routeIs('hrms.leave-structure.index');
    $isPenalty = request()->routeIs('hrms.penalization-policy.index');
    $isOrg = !$isSalary && !$isLeave && !$isPenalty;
@endphp

<div class="settings-sidebar-panel h-100">
    <div class="settings-sidebar-header py-4 px-4 border-bottom">
        <h6 class="fw-bold mb-0 text-dark" style="font-size: 15px; letter-spacing: 0.5px;">Settings</h6>
    </div>
    <div class="settings-sidebar-body py-3 px-3">
        <div class="nav flex-column nav-pills gap-1" id="settingsSubSidebar" role="tablist" aria-orientation="vertical">
            <a class="nav-link {{ $isOrg ? 'active' : '' }} d-flex align-items-center text-start transition-all" id="org-structure-menu" href="{{ route('hrms.org.index') }}" role="tab" aria-controls="org-structure-pane" aria-selected="{{ $isOrg ? 'true' : 'false' }}">
                <i class="feather-settings me-3 fs-16"></i>
                <span>Organization Structure</span>
            </a>
            <a class="nav-link {{ $isSalary ? 'active' : '' }} d-flex align-items-center text-start transition-all" id="salary-structure-menu" href="{{ route('hrms.salary-structure.index') }}" role="tab" aria-controls="salary-structure-pane" aria-selected="{{ $isSalary ? 'true' : 'false' }}">
                <i class="feather-dollar-sign me-3 fs-16"></i>
                <span>Salary Structure</span>
            </a>
            <a class="nav-link {{ $isLeave ? 'active' : '' }} d-flex align-items-center text-start transition-all" id="leave-structure-menu" href="{{ route('hrms.leave-structure.index') }}" role="tab" aria-selected="{{ $isLeave ? 'true' : 'false' }}">
                <i class="feather-calendar me-3 fs-16"></i>
                <span>Leave Structure</span>
            </a>
            <a class="nav-link {{ $isPenalty ? 'active' : '' }} d-flex align-items-center text-start transition-all" id="penalization-policy-menu" href="{{ route('hrms.penalization-policy.index') }}" role="tab" aria-selected="{{ $isPenalty ? 'true' : 'false' }}">
                <i class="feather-alert-octagon me-3 fs-16"></i>
                <span>Penalization Policy</span>
            </a>
        </div>
    </div>
</div>

<style>
    /* Premium dynamic settings sidebar shadow styles */
    #settingsSubSidebar .nav-link {
        background-color: transparent;
        transition: all 0.25s ease-in-out;
        border-radius: 8px !important;
        font-size: 14px;
        font-weight: 500;
        color: #475569 !important;
        padding: 12px 16px !important;
        border: 0 !important;
        display: flex;
        align-items: center;
        width: 100%;
        margin-left: 6px; /* Offset spacing to accommodate the left shadow */
    }
    #settingsSubSidebar .nav-link:hover {
        background-color: #f1f5f9;
        color: #1e293b !important;
    }
    #settingsSubSidebar .nav-link.active {
        background-color: var(--bs-primary) !important; /* Dynamically matches the active primary theme color */
        color: #ffffff !important;
        font-weight: 600;
        border: none !important;
        /* Renders a solid dynamic contrast offset shadow/shape on the left side of the active item */
        box-shadow: -6px 0 0 0 color-mix(in srgb, var(--bs-primary) 70%, #555555) !important;
    }
    #settingsSubSidebar .nav-link i {
        transition: all 0.25s ease;
    }
    #settingsSubSidebar .nav-link.active i {
        color: #ffffff !important;
        transform: scale(1.1);
    }
</style>
