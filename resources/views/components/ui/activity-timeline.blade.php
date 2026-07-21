@props([
    'items' => [],
    'emptyTitle' => 'No Activity Recorded',
    'emptyMessage' => 'No recent timeline activities available for this project.',
])

@php
    $itemsCollection = collect($items);
@endphp

<div {{ $attributes->merge(['class' => 'activity-timeline-container']) }}>
    @if ($itemsCollection->isEmpty())
        <div class="text-center p-4 bg-light rounded-3">
            <div class="avatar-text avatar-lg bg-soft-secondary text-secondary rounded-circle mb-2 mx-auto d-flex align-items-center justify-content-center">
                <i class="feather-clock fs-20"></i>
            </div>
            <h6 class="fw-bold text-dark mb-1 fs-14">{{ __($emptyTitle) }}</h6>
            <p class="fs-12 text-muted mb-0">{{ __($emptyMessage) }}</p>
        </div>
    @else
        <div class="timeline-list position-relative ps-4">
            <!-- Vertical Guide Line -->
            <div class="position-absolute top-0 bottom-0 start-0 ms-2 border-start border-2 border-light"></div>

            @foreach ($itemsCollection as $item)
                @php
                    $user = is_array($item) ? ($item['user'] ?? null) : ($item->user ?? null);
                    $userName = is_array($user) ? ($user['name'] ?? 'System') : (is_object($user) ? ($user->name ?? 'System') : (string)($user ?? 'System'));
                    $userAvatar = is_array($user) ? ($user['avatar'] ?? null) : (is_object($user) ? ($user->avatar ?? null) : null);
                    
                    $timestamp = is_array($item) ? ($item['timestamp'] ?? $item['created_at'] ?? '') : ($item->timestamp ?? $item->created_at ?? '');
                    $title = is_array($item) ? ($item['title'] ?? '') : ($item->title ?? '');
                    $description = is_array($item) ? ($item['description'] ?? null) : ($item->description ?? null);
                    $icon = is_array($item) ? ($item['icon'] ?? 'feather-activity') : ($item->icon ?? 'feather-activity');
                    $color = is_array($item) ? ($item['color'] ?? 'primary') : ($item->color ?? 'primary');
                    $attachments = is_array($item) ? ($item['attachments'] ?? []) : ($item->attachments ?? []);
                @endphp

                <div class="timeline-item position-relative mb-4 pb-1">
                    <!-- Icon / Node Badge -->
                    <div class="position-absolute top-0 start-0 translate-middle-x ms-n4">
                        @if ($userAvatar)
                            <div class="avatar-item avatar-sm">
                                <img src="{{ asset($userAvatar) }}" alt="{{ $userName }}" class="img-fluid rounded-circle border border-2 border-white shadow-xs">
                            </div>
                        @else
                            <div class="avatar-text avatar-sm rounded-circle bg-soft-{{ $color }} text-{{ $color }} border border-2 border-white shadow-xs d-flex align-items-center justify-content-center">
                                <i class="{{ $icon }} fs-12"></i>
                            </div>
                        @endif
                    </div>

                    <!-- Content Box -->
                    <div class="ps-2">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="fs-13 fw-bold text-dark">{{ $userName }}</span>
                            <span class="fs-11 text-muted"><i class="feather-clock me-1"></i>{{ $timestamp }}</span>
                        </div>

                        <h6 class="fs-13 fw-semibold text-dark mb-1">{{ $title }}</h6>

                        @if ($description)
                            <p class="fs-12 text-muted mb-2">{{ $description }}</p>
                        @endif

                        @if (!empty($attachments))
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                @foreach ($attachments as $attachment)
                                    @php
                                        $attName = is_array($attachment) ? ($attachment['name'] ?? 'Attachment') : (string)$attachment;
                                        $attUrl = is_array($attachment) ? ($attachment['url'] ?? '#') : '#';
                                    @endphp
                                    <a href="{{ $attUrl }}" class="badge bg-light text-dark border px-2.5 py-1.5 fs-11 fw-normal text-decoration-none d-inline-flex align-items-center gap-1.5 rounded-2">
                                        <i class="feather-paperclip text-primary fs-12"></i>
                                        <span>{{ $attName }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
