@php
    $avatarVariants = ['primary', 'success', 'info', 'warning', 'danger'];
    $variant = $avatarVariants[$index % count($avatarVariants)];
    $initials = strtoupper(collect(preg_split('/\s+/', trim($name)))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode(''));
@endphp
<div class="avatar-text avatar-md bg-soft-{{ $variant }} text-{{ $variant }} border border-2 border-white collaborator-avatar"
     style="{{ $index > 0 ? 'margin-left: -10px;' : '' }} cursor: pointer;"
     title="{{ $name }}"
     role="button"
     tabindex="0"
     data-member-id="{{ $memberId ?? '' }}"
     data-collaborator-name="{{ $name }}"
     data-collaborator-role="{{ $role ?? '' }}">
    {{ $initials ?: '?' }}
</div>
