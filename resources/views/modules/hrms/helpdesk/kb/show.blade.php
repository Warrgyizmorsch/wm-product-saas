@extends('layouts.duralux')

@section('title', $article->title . ' | Knowledge Base')
@section('page-title', 'Knowledge Base Article')
@section('breadcrumb', 'HRMS / Helpdesk / FAQ')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-secondary" icon="feather-arrow-left" href="{{ route('hrms.helpdesk.kb.index') }}" class="fw-semibold">
            Back to KB Search
        </x-ui.button>
        @if($canManage)
            <x-ui.icon-btn variant="soft-primary" size="sm" icon="feather-edit" title="Edit Article" data-bs-toggle="modal" data-bs-target="#editKbModal{{ $article->id }}" />
            <form action="{{ route('hrms.helpdesk.kb.destroy', $article->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Are you sure you want to delete this article?');">
                @csrf
                @method('DELETE')
                <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" title="Delete Article" />
            </form>
        @endif
    </div>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-5">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
                        <x-ui.badge variant="primary" soft class="fw-semibold fs-7">
                            <i class="feather-tag me-1"></i> {{ $article->category ? $article->category->name : 'General FAQ' }}
                        </x-ui.badge>
                        <div class="text-muted small">
                            <span class="me-3"><i class="feather-eye me-1"></i> {{ $article->view_count }} views</span>
                            <span><i class="feather-calendar me-1"></i> Published {{ $article->created_at->format('M d, Y') }}</span>
                        </div>
                    </div>

                    <h2 class="fw-bold text-dark mb-4">{{ $article->title }}</h2>

                    <div class="article-content text-dark fs-6 leading-relaxed mb-5" style="white-space: pre-line;">
                        {{ $article->content }}
                    </div>

                    <!-- Helpful Footer Box -->
                    <div class="p-4 bg-light rounded-3 text-center border">
                        <h6 class="fw-bold text-dark mb-2">Still need help with this topic?</h6>
                        <p class="text-muted small mb-3">If this article didn't resolve your query, submit a ticket to our HR Support team.</p>
                        <x-ui.button variant="primary" icon="feather-life-buoy" href="{{ route('hrms.helpdesk.tickets.index') }}" class="fw-bold">
                            Submit a Ticket
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canManage)
<x-ui.modal id="editKbModal{{ $article->id }}" title="<i class='feather-edit me-2'></i> Edit Article: {{ $article->title }}" size="lg" formAction="{{ route('hrms.helpdesk.kb.update', $article->id) }}" formMethod="PUT" submitText="Save Changes" centered>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" label="Article Title" name="title" value="{{ $article->title }}" :required="true" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="select" label="Category" name="category_id">
            <option value="">-- Select Category --</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ $article->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">Content / Explanation <span class="text-danger">*</span></label>
        <textarea name="content" class="form-control" rows="8" required>{{ $article->content }}</textarea>
    </div>
    <div class="mb-3">
        <x-ui.checkbox label="Publish immediately to Knowledge Base" name="is_published" value="1" :checked="$article->is_published" id="editPubSwitchShow" />
    </div>
</x-ui.modal>
@endif
@endsection
