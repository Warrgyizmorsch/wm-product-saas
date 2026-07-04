@props([
    'id' => null,
    'title' => 'Action Execute Successfully',
    'type' => 'success',
    'position' => 'top-end',
    'delay' => 3000,
])

<a href="javascript:void(0);"
   id="{{ $id }}"
   {{ $attributes->merge(['class' => '']) }}>
    {{ $slot }}
</a>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById(@json($id));

        if (!el) return;

        el.addEventListener('click', function (e) {
            e.preventDefault();

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
        });
    });
</script>
@endpush