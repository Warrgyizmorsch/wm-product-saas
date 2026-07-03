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
