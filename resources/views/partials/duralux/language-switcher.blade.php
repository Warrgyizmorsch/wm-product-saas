@php
    $currentLanguage = $currentLanguage ?? config('localization.supported.' . app()->getLocale(), config('localization.supported.en'));
    $supportedLanguages = $supportedLanguages ?? config('localization.supported', []);
    $languages = collect($supportedLanguages)
        ->map(fn (array $language, string $locale) => [
            'locale' => $locale,
            'code' => $language['flag'],
            'name' => $language['name'],
            'native' => $language['native'],
            'active' => app()->getLocale() === $locale,
        ])
        ->values();
@endphp

<div class="dropdown nxl-h-item nxl-header-language">
    <a href="javascript:void(0);" class="nxl-head-link me-0 nxl-language-link" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="{{ __('ui.select_language') }}">
        <img src="{{ asset('assets/vendors/img/flags/4x3/' . ($currentLanguage['flag'] ?? 'us') . '.svg') }}" alt="{{ $currentLanguage['name'] ?? 'English' }}" class="img-fluid wd-20">
    </a>
    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-language-dropdown">
        <div class="dropdown-divider mt-0"></div>
        <div class="language-items-wrapper">
            <div class="select-language px-4 py-2 hstack justify-content-between gap-4">
                <div class="lh-lg">
                    <h6 class="mb-0">{{ __('ui.select_language') }}</h6>
                    <p class="fs-11 text-muted mb-0">{{ __('ui.language_count', ['count' => $languages->count()]) }}</p>
                </div>
                <span class="avatar-text avatar-md" data-bs-toggle="tooltip" title="{{ __('ui.select_language') }}">
                    <i class="feather-globe"></i>
                </span>
            </div>
            <div class="dropdown-divider"></div>
            <div class="row px-4 pt-3">
                @foreach ($languages as $language)
                    <div class="col-sm-4 col-6 language_select {{ !empty($language['active']) ? 'active' : '' }}">
                        <a href="{{ route('locale.switch', $language['locale']) }}" class="d-flex align-items-center gap-2" data-language="{{ $language['name'] }}" data-flag="{{ asset('assets/vendors/img/flags/4x3/' . $language['code'] . '.svg') }}">
                            <div class="avatar-image avatar-sm">
                                <img src="{{ asset('assets/vendors/img/flags/1x1/' . $language['code'] . '.svg') }}" alt="{{ $language['name'] }}" class="img-fluid">
                            </div>
                            <span>{{ $language['native'] }}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
