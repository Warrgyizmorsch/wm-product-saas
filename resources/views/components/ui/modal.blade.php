@props([
    'id',
    'title' => 'Modal Title',
    'size' => null, // sm, lg, xl
    'centered' => false,
    'scrollable' => false,
    'static' => false, // backdrop static
    'submitText' => 'Save changes',
    'closeText' => 'Close',
    'formAction' => null,
    'formMethod' => 'POST'
])

<div class="modal fade" id="{{ $id }}" {{ $static ? 'data-bs-backdrop=static data-bs-keyboard=false' : '' }} tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true" {{ $attributes->merge(['class' => '']) }}>
    <div class="modal-dialog {{ $centered ? 'modal-dialog-centered' : '' }} {{ $scrollable ? 'modal-dialog-scrollable' : '' }} {{ $size ? 'modal-' . $size : '' }}">
        <div class="modal-content">
            @if($formAction)
                <form method="{{ in_array(strtoupper($formMethod), ['GET', 'POST']) ? $formMethod : 'POST' }}" action="{{ $formAction }}">
                    @csrf
                    @if(!in_array(strtoupper($formMethod), ['GET', 'POST']))
                        @method($formMethod)
                    @endif
            @endif

            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}Label">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body">
                {{ $slot }}
            </div>

            <div class="modal-footer">
                @if(isset($footer))
                    {{ $footer }}
                @else
                    <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ $closeText }}</button>
                    @if($formAction)
                        <button type="submit" class="btn btn-primary">{{ $submitText }}</button>
                    @else
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">{{ $submitText }}</button>
                    @endif
                @endif
            </div>

            @if($formAction)
                </form>
            @endif
        </div>
    </div>
</div>

<script>
    (function () {
        var modalEl = document.getElementById('{{ $id }}');
        if (modalEl) {
            document.body.appendChild(modalEl);
        }
    })();
</script>
