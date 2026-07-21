@props([
    'users' => [],
    'size' => 'md', // xs, sm, md, lg
    'max' => 4,
    'shape' => 'circle', // circle, rounded
])

@php
    $sizeClasses = [
        'xs' => 'avatar-xs',
        'sm' => 'avatar-sm',
        'md' => 'avatar-md',
        'lg' => 'avatar-lg',
    ];
    $sizeClass = $sizeClasses[$size] ?? 'avatar-md';
    $usersCollection = collect($users);
    $totalCount = $usersCollection->count();
    $visibleUsers = $usersCollection->take($max);
    $overflowCount = max(0, $totalCount - $max);
@endphp

<div {{ $attributes->merge(['class' => 'avatar-group d-flex align-items-center']) }}>
    @foreach ($visibleUsers as $user)
        @php
            $name = is_array($user) ? ($user['name'] ?? $user['email'] ?? 'User') : (is_object($user) ? ($user->name ?? $user->email ?? 'User') : (string)$user);
            $image = is_array($user) ? ($user['avatar'] ?? $user['image'] ?? null) : (is_object($user) ? ($user->avatar ?? $user->image ?? null) : null);
            $isOnline = is_array($user) ? ($user['online'] ?? $user['is_online'] ?? false) : (is_object($user) ? ($user->online ?? $user->is_online ?? false) : false);

            // Compute initials
            $words = explode(' ', trim($name));
            $initials = count($words) >= 2
                ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1))
                : mb_strtoupper(mb_substr($name, 0, 2));
        @endphp

        <div class="avatar-item {{ $sizeClass }} position-relative {{ $loop->first ? '' : 'ms-n2' }}"
             data-bs-toggle="tooltip"
             data-bs-placement="top"
             title="{{ $name }}">
            @if ($image)
                <img src="{{ asset($image) }}" alt="{{ $name }}" class="img-fluid {{ $shape === 'circle' ? 'rounded-circle' : 'rounded' }} border border-2 border-white shadow-sm">
            @else
                <span class="avatar-text {{ $shape === 'circle' ? 'rounded-circle' : 'rounded' }} bg-soft-primary text-primary fw-semibold border border-2 border-white shadow-sm d-flex align-items-center justify-content-center w-100 h-100">
                    {{ $initials }}
                </span>
            @endif

            @if ($isOnline)
                <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-white rounded-circle">
                    <span class="visually-hidden">Online</span>
                </span>
            @endif
        </div>
    @endforeach

    @if ($overflowCount > 0)
        <div class="avatar-item {{ $sizeClass }} ms-n2" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $overflowCount }} {{ __('more') }}">
            <span class="avatar-text {{ $shape === 'circle' ? 'rounded-circle' : 'rounded' }} bg-light text-muted fw-bold border border-2 border-white shadow-sm d-flex align-items-center justify-content-center w-100 h-100 fs-12">
                +{{ $overflowCount }}
            </span>
        </div>
    @endif
</div>
