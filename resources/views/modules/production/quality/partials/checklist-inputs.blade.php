{{--
    QC checklist inputs for one or more Quality Plans (docs/production_quality_rules.md QR-16, QP-04/05).
    Used by the operator QC form and the WIP final QC form.
    - Mandatory plans are always inspected; optional plans can be included with their tick.
    - Mandatory parameters of an inspected plan are required.
    - Field names: parameter_values[<plan_id>][<parameter_id>], inspected_plans[] (optional plans that were included).
    @var \Illuminate\Support\Collection $checklistPlans  items: ['plan' => ProductionQualityPlan (parameters loaded), 'is_mandatory' => bool, 'source' => ?string]
    @var string $idPrefix
--}}
@php $idPrefix = $idPrefix ?? 'qc'; @endphp

@if ($checklistPlans->isEmpty())
    <div class="p-3 border rounded bg-light fs-12 text-muted">
        <i class="feather-info text-primary me-1"></i>No quality plan is attached here — record the accepted and rejected quantities only.
    </div>
@else
    <div class="d-flex flex-column gap-3">
        @foreach ($checklistPlans as $entry)
            @php
                $plan = $entry['plan'];
                $planMandatory = (bool) $entry['is_mandatory'];
                $cardId = $idPrefix . '_plan_' . $plan->id;
            @endphp
            <div class="qc-plan-card border rounded p-3 bg-light" id="{{ $cardId }}">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <div class="fw-bold text-dark fs-13">{{ $plan->name }} <span class="text-muted fw-normal">v{{ $plan->version }}</span></div>
                        <div class="fs-11 text-muted">
                            {{ $plan->plan_number }}
                            @if (($entry['source'] ?? null) === 'sub_assembly')
                                · <span class="text-warning">QC of a semi made in this order</span>
                            @endif
                        </div>
                    </div>
                    @if ($planMandatory)
                        <span class="badge bg-soft-danger text-danger fs-10">Mandatory plan</span>
                        <input type="hidden" name="inspected_plans[]" value="{{ $plan->id }}">
                    @else
                        <label class="d-flex align-items-center gap-1 fs-12 text-dark mb-0">
                            <input type="checkbox" class="form-check-input mt-0" name="inspected_plans[]" value="{{ $plan->id }}"
                                   onchange="(function(cb){ var card = cb.closest('.qc-plan-card'); card.querySelectorAll('.qc-param').forEach(function(el){ el.disabled = !cb.checked; if (el.dataset.mandatory === '1') { el.required = cb.checked; } }); card.querySelector('.qc-plan-body').style.opacity = cb.checked ? '1' : '.5'; })(this)">
                            Inspect this optional plan
                        </label>
                    @endif
                </div>

                <div class="qc-plan-body" style="{{ $planMandatory ? '' : 'opacity:.5' }}">
                    @forelse ($plan->parameters as $param)
                        @php
                            $fieldName = 'parameter_values[' . $plan->id . '][' . $param->id . ']';
                            $fieldId = $cardId . '_p' . $param->id;
                            $required = $planMandatory && $param->is_mandatory;
                            $old = old('parameter_values.' . $plan->id . '.' . $param->id);
                        @endphp
                        <div class="row g-2 align-items-center py-1 border-bottom">
                            <div class="col-md-5">
                                <label for="{{ $fieldId }}" class="fs-12 text-dark mb-0">
                                    {{ $param->name }} @if ($param->is_mandatory)<span class="text-danger">*</span>@endif
                                </label>
                                <div class="fs-11 text-muted">
                                    {{ $param->type === 'text' ? 'Notes' : $param->limitsLabel() }}
                                </div>
                            </div>
                            <div class="col-md-7">
                                @if ($param->type === 'numeric')
                                    <input type="number" step="any" class="form-control form-control-sm qc-param" id="{{ $fieldId }}" name="{{ $fieldName }}"
                                           value="{{ $old }}" placeholder="Measured value{{ $param->unit_of_measure ? ' (' . $param->unit_of_measure . ')' : '' }}"
                                           data-mandatory="{{ $param->is_mandatory ? 1 : 0 }}" @required($required) @disabled(!$planMandatory)>
                                @elseif ($param->type === 'pass_fail')
                                    <select class="form-select form-select-sm qc-param" id="{{ $fieldId }}" name="{{ $fieldName }}"
                                            data-mandatory="{{ $param->is_mandatory ? 1 : 0 }}" @required($required) @disabled(!$planMandatory)>
                                        <option value="">Choose result…</option>
                                        <option value="pass" @selected($old === 'pass')>Pass</option>
                                        <option value="fail" @selected($old === 'fail')>Fail</option>
                                    </select>
                                @else
                                    <input type="text" class="form-control form-control-sm qc-param" id="{{ $fieldId }}" name="{{ $fieldName }}"
                                           value="{{ $old }}" placeholder="Observation / notes"
                                           data-mandatory="{{ $param->is_mandatory ? 1 : 0 }}" @required($required) @disabled(!$planMandatory)>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="fs-12 text-muted">This plan has no checklist parameters.</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
@endif
