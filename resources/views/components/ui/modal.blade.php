@props([
    'id',
    'title' => 'Modal Title',
    'size' => null, // sm, lg, xl
    'centered' => true,
    'scrollable' => true,
    'static' => false, // backdrop static
    'submitText' => 'Save changes',
    'closeText' => 'Close',
    'formAction' => null,
    'formMethod' => 'POST',
    'showFooter' => true
])

<div class="modal fade" id="{{ $id }}" {{ $static ? 'data-bs-backdrop=static data-bs-keyboard=false' : '' }} tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true" {{ $attributes->merge(['class' => '']) }}>
    <div class="modal-dialog {{ $centered ? 'modal-dialog-centered' : '' }} {{ $scrollable ? 'modal-dialog-scrollable' : '' }} {{ $size ? 'modal-' . $size : '' }}">
        <div class="modal-content">
            @if($formAction)
                <form method="{{ in_array(strtoupper($formMethod), ['GET', 'POST']) ? $formMethod : 'POST' }}" action="{{ $formAction }}" class="d-flex flex-column h-100 min-vh-0 overflow-hidden">
                    @csrf
                    @if(!in_array(strtoupper($formMethod), ['GET', 'POST']))
                        @method($formMethod)
                    @endif
            @endif

            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}Label">{!! $title !!}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body">
                {{ $slot }}
            </div>

            @if($showFooter)
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
            @endif

            @if($formAction)
                </form>
            @endif
        </div>
    </div>
</div>

<style>
    #{{ $id }} .modal-dialog-scrollable {
        max-height: calc(100% - 3.5rem);
    }
    #{{ $id }} .modal-dialog-scrollable .modal-content {
        max-height: calc(100vh - 3.5rem);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    #{{ $id }} .modal-dialog-scrollable .modal-content > form {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        max-height: 100%;
        overflow: hidden;
        height: 100%;
    }
    #{{ $id }} .modal-dialog-scrollable .modal-header,
    #{{ $id }} .modal-dialog-scrollable .modal-footer {
        flex-shrink: 0;
    }
    #{{ $id }} .modal-dialog-scrollable .modal-body {
        overflow-y: auto;
        overflow-x: hidden;
        flex: 1 1 auto;
        min-height: 0;
        overscroll-behavior: contain;
    }
</style>

<script>
    (function () {
        var modalEl = document.getElementById('{{ $id }}');
        if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
    })();
</script>
