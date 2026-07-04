@props([
    'id' => null,
    'title' => 'Action Execute Successfully',
    'type' => 'success',
    'position' => 'top-end',
    'delay' => 3000,
    'auto' => false,
])

@if(!$auto)
<a href="javascript:void(0);"
   id="{{ $id ?? 'toast_' . uniqid() }}"
   {{ $attributes->merge(['class' => '']) }}>
    {{ $slot }}
</a>
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        function showToast() {
            if (typeof Swal !== 'undefined') {
                Swal.mixin({
                    toast: true,
                    position: @json($position),
                    showConfirmButton: false,
                    timer: {{ $delay }},
                    timerProgressBar: true,
                    didOpen: function (toast) {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                    }
                }).fire({
                    icon: @json($type),
                    title: @json($title)
                });
            }
        }

        @if($auto)
            showToast();
        @else
            const el = document.getElementById(@json($id));
            if (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    showToast();
                });
            }
        @endif
    });
</script>
@endpush