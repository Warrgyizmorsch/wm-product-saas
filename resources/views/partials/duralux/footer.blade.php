{{-- System footer bar (ui-reference "SystemGlobalFooter"). --}}
@php
    $shell ??= app(\App\Support\ShellContext::class)->resolve();
    $tz = $shell['tenant']?->timezone ?: config('app.timezone');
    $now = now($tz);
@endphp
<footer class="footer ax-footer">
    <div class="ax-footer-group">
        <span class="d-inline-flex align-items-center gap-2">
            <span class="ax-dot is-positive"></span>{{ config('app.name') }}
            <strong class="ax-mono">Laravel {{ app()->version() }}</strong>
        </span>
        <span class="ax-footer-sep d-none d-md-inline">|</span>
        <span class="ax-mono d-none d-md-inline">Tenant: {{ $shell['tenant_code'] }}</span>
        <span class="ax-footer-sep d-none d-lg-inline">|</span>
        <span class="d-none d-lg-inline">{{ $shell['fiscal_year'] }}</span>
    </div>
    <div class="ax-footer-group">
        @if (auth()->check() && \Illuminate\Support\Facades\Route::has('access.audit-log.index')
            && app(\App\Services\Access\AccessService::class)->allows(auth()->user(), 'audit.logs.view', ['tenant_id' => tenant_id()]))
            <a href="{{ route('access.audit-log.index') }}">{{ __('Audit Logs') }}</a>
        @endif
        <a href="javascript:void(0);" onclick="document.dispatchEvent(new KeyboardEvent('keydown', {key: 'k', ctrlKey: true}))" title="Ctrl/⌘ + K">{{ __('Shortcut: Ctrl K') }}</a>
        <span class="ax-mono ax-footer-clock" data-tz="{{ $tz }}">{{ $now->format('h:i A') }} {{ $now->format('T') }}</span>
    </div>
</footer>
<script>
    // Keep the footer clock live without a reload.
    (function () {
        var el = document.querySelector('.ax-footer-clock');
        if (!el || !window.Intl) return;
        var fmt = new Intl.DateTimeFormat('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: el.dataset.tz, timeZoneName: 'short' });
        setInterval(function () { el.textContent = fmt.format(new Date()).toUpperCase(); }, 30000);
    })();
</script>
