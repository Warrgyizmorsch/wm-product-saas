@props([
    'workOrder',
    'assignments' => null,
    'variant' => 'summary', // 'trigger', 'summary', or 'modal'
    'size' => null,
])

@if($variant === 'trigger')
    <x-ui.assigned-technicians-trigger :workOrder="$workOrder" :assignments="$assignments" :size="$size" {{ $attributes }} />
@elseif($variant === 'modal')
    <x-ui.assigned-technicians-modal :workOrder="$workOrder" :assignments="$assignments" {{ $attributes }} />
@else
    <x-ui.assigned-technicians-summary :workOrder="$workOrder" :assignments="$assignments" {{ $attributes }} />
@endif
