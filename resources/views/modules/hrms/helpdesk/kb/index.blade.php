@extends('layouts.duralux')

@section('title', 'Knowledge Base & FAQ | Helpdesk')
@section('page-title', 'Knowledge Base & Self-Service FAQ')
@section('breadcrumb', 'HRMS / Helpdesk / FAQ')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-secondary" icon="feather-arrow-left" href="{{ route('hrms.helpdesk.tickets.index') }}">
            Back to Helpdesk
        </x-ui.button>
        @if($canManage)
            <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="modal" data-bs-target="#createKbModal" class="fw-bold">
                Create Article
            </x-ui.button>
        @endif
    </div>
@endsection

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="feather-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Search & Filter Header Bar -->
        <div class="border-bottom pb-4 mb-4">
            <div class="row align-items-center justify-content-between g-3">
                <div class="col-md-6">
                    <h5 class="fw-bold text-dark mb-1"><i class="feather-book-open text-primary me-2"></i> HR Knowledge Base & FAQ</h5>
                    <p class="text-muted small mb-0">Search company HR policies, procedures, tax guides, and FAQs.</p>
                </div>
                <div class="col-md-6">
                    <form method="GET" action="{{ route('hrms.helpdesk.kb.index') }}" id="kbSearchForm" class="d-flex align-items-center justify-content-md-end gap-2">
                        <!-- Instant Search Input -->
                        <div class="d-flex align-items-center bg-light border rounded px-3 py-1 flex-grow-1" style="max-width: 340px;">
                            <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                            <input type="text" name="search" id="kbSearchInput" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search by topic, leave policy, PF..." value="{{ request('search') }}" style="box-shadow: none; height: 32px;" autocomplete="off">
                        </div>

                        <!-- Common UI Filter Component -->
                        <x-ui.filter label="Filter" resetUrl="{{ route('hrms.helpdesk.kb.index') }}">
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Category Filter</label>
                                <x-ui.odoo-form-ui type="select" name="category_id">
                                    <option value="">All Categories</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        </x-ui.filter>
                    </form>
                </div>
            </div>
        </div>

        <!-- Article Cards Grid -->
        <div class="row g-4">
            @forelse($articles as $art)
                <div class="col-xl-4 col-md-6">
                    <div class="card rounded-3 h-100 shadow-sm transition-all bg-white" style="border: 1px solid #dbe2ea !important;">
                        <div class="card-body p-4 d-flex flex-column justify-content-between">
                            <div>
                                <!-- Header Badge & View Counter -->
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <x-ui.badge variant="primary" soft class="fw-semibold">
                                        <i class="feather-tag me-1"></i> {{ $art->category ? $art->category->name : 'General Policy' }}
                                    </x-ui.badge>
                                    <div class="d-flex align-items-center gap-2 text-muted small">
                                        <span><i class="feather-eye me-1"></i> {{ $art->view_count }} views</span>
                                        @if(!$art->is_published)
                                            <x-ui.badge variant="warning" soft>Draft</x-ui.badge>
                                        @endif
                                    </div>
                                </div>

                                <!-- Title & Preview Content -->
                                <h6 class="fw-bold text-dark mb-2 fs-15 line-clamp-2">{{ $art->title }}</h6>
                                <p class="text-muted small leading-relaxed mb-0">
                                    {{ Str::limit(strip_tags($art->content), 240) }}
                                </p>
                            </div>

                            <!-- Card Footer: Link & Actions -->
                            <div class="pt-3 border-top mt-3 d-flex align-items-center justify-content-between">
                                <a href="{{ route('hrms.helpdesk.kb.show', $art->slug) }}" class="fw-bold text-primary text-decoration-none fs-14 d-inline-flex align-items-center">
                                    Read Full Guide <i class="feather-arrow-right ms-1.5 fs-15"></i>
                                </a>

                                @if($canManage)
                                    <div class="d-flex align-items-center gap-1">
                                        <x-ui.icon-btn variant="soft-primary" size="sm" icon="feather-edit" title="Edit Article" data-bs-toggle="modal" data-bs-target="#editKbModal{{ $art->id }}" />
                                        <form action="{{ route('hrms.helpdesk.kb.destroy', $art->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Delete this article?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" title="Delete Article" />
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Article Modal -->
                @if($canManage)
                <x-ui.modal id="editKbModal{{ $art->id }}" title="<i class='feather-edit me-2'></i> Edit Article: {{ $art->title }}" size="lg" formAction="{{ route('hrms.helpdesk.kb.update', $art->id) }}" formMethod="PUT" submitText="Save Changes" centered>
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" label="Article Title" name="title" value="{{ $art->title }}" :required="true" />
                    </div>
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="Category" name="category_id">
                            <option value="">-- Select Category --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ $art->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Content / Explanation <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control" rows="8" required>{{ $art->content }}</textarea>
                    </div>
                    <div class="mb-3">
                        <x-ui.checkbox label="Publish immediately to Knowledge Base" name="is_published" value="1" :checked="$art->is_published" id="editPubSwitch_{{ $art->id }}" />
                    </div>
                </x-ui.modal>
                @endif
            @empty
                <div class="col-12 py-5 text-center">
                    <div class="text-muted">
                        <i class="feather-book-open fs-1 text-secondary mb-2 d-block"></i>
                        <h6 class="fw-bold text-dark mb-1">No knowledge base articles found</h6>
                        <p class="text-muted small mb-0">Try searching with a different term or submit a helpdesk ticket for personal assistance.</p>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="d-flex justify-content-end mt-4">
            {{ $articles->withQueryString()->links() }}
        </div>
    </div>
</div>

<!-- Modal: Create KB Article -->
@if($canManage)
<x-ui.modal id="createKbModal" title="<i class='feather-plus-circle me-2'></i> Create Knowledge Base Article" size="lg" formAction="{{ route('hrms.helpdesk.kb.store') }}" submitText="Save Article" centered>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" label="Article Title" name="title" placeholder="e.g. Leave Encashment Policy & Process" :required="true" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="select" label="Category" name="category_id">
            <option value="">-- Select Category --</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">Content / Explanation <span class="text-danger">*</span></label>
        <textarea name="content" class="form-control" rows="8" placeholder="Provide step-by-step instructions, policies, or answer FAQs..." required></textarea>
    </div>
    <div class="mb-3">
        <x-ui.checkbox label="Publish immediately to Knowledge Base" name="is_published" value="1" :checked="true" id="pubSwitch" />
    </div>
</x-ui.modal>
@endif

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const kbSearchInput = document.getElementById('kbSearchInput');
        const kbSearchForm = document.getElementById('kbSearchForm');
        let searchTimer;

        if (kbSearchInput && kbSearchForm) {
            // Auto-focus search input if search query is present
            if (kbSearchInput.value) {
                kbSearchInput.focus();
                const len = kbSearchInput.value.length;
                kbSearchInput.setSelectionRange(len, len);
            }

            kbSearchInput.addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    kbSearchForm.submit();
                }, 500);
            });
        }
    });
</script>
@endpush
@endsection
