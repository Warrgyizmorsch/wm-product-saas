<div class="row">
    <!-- List Table Card -->
    <div class="col-12">
        <x-ui.card title="{{ __('hrms.org.shifts') }}" stretch bodyClass="p-0">
            <x-slot name="headerAction">
                <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#addShiftModal">
                    {{ __('hrms.org.add_shift') }}
                </x-ui.button>
            </x-slot>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="60">{{ __('hrms.org.tbl_hash') }}</th>
                            <th>{{ __('hrms.org.tbl_code') }}</th>
                            <th>{{ __('hrms.org.tbl_shift_name') }}</th>
                            <th>{{ __('hrms.employees.lbl_start_time') ?? 'Start Time' }}</th>
                            <th>{{ __('hrms.employees.lbl_end_time') ?? 'End Time' }}</th>
                            <th>{{ __('hrms.employees.lbl_break_duration') ?? 'Break Duration' }}</th>
                            <th>{{ __('hrms.employees.lbl_overtime_allowed') ?? 'Overtime Allowed' }}</th>
                            <th>{{ __('hrms.org.tbl_status') }}</th>
                            <th width="150" class="text-end">{{ __('hrms.org.tbl_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shifts as $sf)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><code>{{ $sf->code }}</code></td>
                            <td><span class="fw-bold text-dark">{{ $sf->name }}</span></td>
                            <td><span class="font-monospace text-muted">{{ substr($sf->start_time, 0, 5) }}</span></td>
                            <td><span class="font-monospace text-muted">{{ substr($sf->end_time, 0, 5) }}</span></td>
                            <td><span>{{ $sf->break_minutes ?? 0 }} {{ __('hrms.employees.lbl_mins') ?? 'mins' }}</span></td>
                            <td>
                                @if($sf->overtime_allowed)
                                    <x-ui.badge variant="success" soft>{{ __('hrms.employees.mdl_yes') ?? 'Yes' }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" soft>{{ __('hrms.employees.mdl_no') ?? 'No' }}</x-ui.badge>
                                @endif
                            </td>
                            <td>
                                @if($sf->active)
                                    <x-ui.badge variant="success" soft>{{ __('hrms.employees.frm_status_active') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="danger" soft>{{ __('hrms.employees.frm_status_inactive') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('hrms.shift.destroy', $sf->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('hrms.org.confirm_delete_shift') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <div class="hstack gap-2 justify-content-end">
                                        <x-ui.action-dropdown>
                                            <li>
                                                <a class="dropdown-item btn-edit-shift" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#editShiftModal" data-shift="{{ base64_encode($sf->toJson()) }}">
                                                    <i class="feather feather-edit-3 me-3"></i>
                                                    <span>{{ __('hrms.assets.edit') }}</span>
                                                </a>
                                            </li>
                                            <li class="dropdown-divider"></li>
                                            <li>
                                                <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start d-flex align-items-center">
                                                    <i class="feather feather-trash-2 me-3"></i>
                                                    <span>{{ __('hrms.assets.delete') }}</span>
                                                </button>
                                            </li>
                                        </x-ui.action-dropdown>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                        @if($shifts->isEmpty())
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                {{ __('hrms.org.empty_shift') }}
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>
</div>

<script>
    (function() {
        function init() {
            // Edit Action Trigger
            document.querySelectorAll('.btn-edit-shift').forEach(btn => {
                btn.addEventListener('click', function() {
                    let shift = JSON.parse(atob(this.dataset.shift));
                    
                    let nameEl = document.getElementById('edit_shift_name');
                    if (nameEl) nameEl.value = shift.name || '';
                    
                    let codeEl = document.getElementById('edit_shift_code');
                    if (codeEl) codeEl.value = shift.code || '';
                    
                    let startEl = document.getElementById('edit_shift_start');
                    if (startEl) startEl.value = shift.start_time ? shift.start_time.substring(0, 5) : '';
                    
                    let endEl = document.getElementById('edit_shift_end');
                    if (endEl) endEl.value = shift.end_time ? shift.end_time.substring(0, 5) : '';
                    
                    let breakEl = document.getElementById('edit_shift_break');
                    if (breakEl) breakEl.value = shift.break_minutes || 0;
                    
                    let overtimeSelect = document.getElementById('edit_shift_overtime');
                    if (overtimeSelect) {
                        overtimeSelect.value = (shift.overtime_allowed === true || shift.overtime_allowed === 1 || shift.overtime_allowed === '1') ? '1' : '0';
                        if (window.jQuery && $(overtimeSelect).hasClass('select2-hidden-accessible')) {
                            $(overtimeSelect).trigger('change');
                        }
                    }
                    
                    let activeSelect = document.getElementById('edit_shift_active');
                    if (activeSelect) {
                        activeSelect.value = (shift.active === true || shift.active === 1 || shift.active === '1') ? '1' : '0';
                        if (window.jQuery && $(activeSelect).hasClass('select2-hidden-accessible')) {
                            $(activeSelect).trigger('change');
                        }
                    }
                    
                    let form = document.getElementById('shift_edit_form');
                    if (form) {
                        form.action = '/hrms/org/shift/update/' + shift.id;
                    }
                });
            });
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", init);
        } else {
            init();
        }
    })();
</script>
