{{--
    Reopens whichever project modal (quick-create or an edit modal) last failed validation,
    identified by the hidden "_modal" input each of those forms submits. Needed because
    create/edit no longer have a dedicated page to fall back on and show errors inline.
--}}
@if ($errors->any() && old('_modal'))
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById(@js(old('_modal')));
                if (modalEl && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });
        </script>
    @endpush
@endif
