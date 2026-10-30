@extends('layouts.duralux')

@section('title', __('hrms.helpdesk.title_kb_show', ['title' => $article->title]))
@section('page-title', __('hrms.helpdesk.page_title_kb_show'))
@section('breadcrumb', __('hrms.helpdesk.breadcrumb_kb'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button variant="outline-secondary" icon="feather-arrow-left" href="{{ route('hrms.helpdesk.kb.index') }}" class="fw-semibold">
            {{ __('hrms.helpdesk.btn_back_to_kb') }}
        </x-ui.button>
        @if($canManage)
            <x-ui.icon-btn variant="soft-primary" size="sm" icon="feather-edit" :title="__('hrms.helpdesk.btn_edit_article')" data-bs-toggle="modal" data-bs-target="#editKbModal{{ $article->id }}" />
            <form action="{{ route('hrms.helpdesk.kb.destroy', $article->id) }}" method="POST" class="d-inline-block" onsubmit="return confirm(@json(__('hrms.helpdesk.confirm_delete_article')));">
                @csrf
                @method('DELETE')
                <x-ui.icon-btn type="submit" variant="soft-danger" size="sm" icon="feather-trash-2" :title="__('hrms.helpdesk.btn_delete_article')" />
            </form>
        @endif
    </div>
@endsection

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <x-ui.alert variant="success" dismissible class="mb-4">
            <i class="feather-check-circle me-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" dismissible class="mb-4">
            <i class="feather-alert-triangle me-2"></i>{{ session('error') }}
        </x-ui.alert>
    @endif
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-5">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-3">
                        <x-ui.badge variant="primary" soft class="fw-semibold fs-7">
                            <i class="feather-tag me-1"></i> {{ $article->category ? $article->category->name : __('hrms.helpdesk.badge_general_faq') }}
                        </x-ui.badge>
                        <div class="text-muted small">
                            <span class="me-3"><i class="feather-eye me-1"></i> {{ __('hrms.helpdesk.views_count', ['count' => $article->view_count]) }}</span>
                            <span><i class="feather-calendar me-1"></i> {{ __('hrms.helpdesk.published_on', ['date' => $article->created_at->format('M d, Y')]) }}</span>
                        </div>
                    </div>

                    <h2 class="fw-bold text-dark mb-4">{{ $article->title }}</h2>

                    <div class="article-content text-dark fs-6 leading-relaxed mb-5" style="white-space: pre-line;">
                        {!! $article->content !!}
                    </div>

                    <!-- Helpful Footer Box -->
                    <div class="p-4 bg-light rounded-3 text-center border">
                        <h6 class="fw-bold text-dark mb-2">{{ __('hrms.helpdesk.still_need_help_title') }}</h6>
                        <p class="text-muted small mb-3">{{ __('hrms.helpdesk.still_need_help_subtitle') }}</p>
                        <x-ui.button variant="primary" icon="feather-life-buoy" href="{{ route('hrms.helpdesk.tickets.index') }}" class="fw-bold">
                            {{ __('hrms.helpdesk.btn_submit_ticket') }}
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canManage)
<x-ui.modal id="editKbModal{{ $article->id }}" :title="'<i class=\'feather-edit me-2\'></i> ' . __('hrms.helpdesk.modal_edit_kb_title', ['title' => $article->clean_title])" size="lg" formAction="{{ route('hrms.helpdesk.kb.update', $article->id) }}" formMethod="PUT" :submitText="__('hrms.helpdesk.btn_save_changes')" centered>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="input" :label="__('hrms.helpdesk.field_article_title')" name="title" :value="$article->clean_title" :required="true" />
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="select" :label="__('hrms.helpdesk.field_category')" name="category_id">
            <option value="">{{ __('hrms.helpdesk.select_category_placeholder') }}</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ $article->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </x-ui.odoo-form-ui>
    </div>
    <div class="mb-3">
        <x-ui.odoo-form-ui type="textarea" :label="__('hrms.helpdesk.field_content_explanation')" name="content" rows="8" :required="true">{{ $article->clean_content }}</x-ui.odoo-form-ui>
    </div>
    <div class="mb-3">
        <x-ui.checkbox :label="__('hrms.helpdesk.field_publish_immediately')" name="is_published" value="1" :checked="$article->is_published" id="editPubSwitchShow" />
    </div>
</x-ui.modal>
@endif
@endsection
