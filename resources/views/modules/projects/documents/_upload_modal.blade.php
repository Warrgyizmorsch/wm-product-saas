<x-ui.modal id="uploadDocumentModal" :title="__('projects.upload_document')" size="md" :showFooter="false">
    <form id="uploadDocumentForm" method="POST" action="{{ route('projects.documents.store', $project) }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_document_form" value="upload">
        <input type="hidden" name="_modal" value="uploadDocumentModal">
        @if (isset($attachableType) && isset($attachableId))
            <input type="hidden" name="attachable_type" value="{{ $attachableType }}">
            <input type="hidden" name="attachable_id" value="{{ $attachableId }}">
        @endif

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.category') }} <span class="text-danger">*</span></label>
                <x-ui.odoo-form-ui type="select" name="category" required>
                    @foreach (\App\Domains\Projects\Models\ProjectDocument::CATEGORIES as $cat)
                        <option value="{{ $cat }}" @selected(old('category', \App\Domains\Projects\Models\ProjectDocument::CATEGORY_ATTACHMENT) === $cat)>
                            {{ __('projects.document_categories.' . $cat) }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.file_name') }} ({{ __('ui.optional') }})</label>
                <x-ui.odoo-form-ui type="input" name="title" :placeholder="__('projects.file_name')" :value="old('title')" />
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.document') }} <span class="text-danger">*</span></label>
                <input type="file" name="file" class="form-control form-control-sm" required>
                <div class="form-text fs-11 text-muted">{{ __('projects.max_file_size_hint') }}</div>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.file_remarks') }}</label>
                <x-ui.odoo-form-ui type="textarea" name="remarks" rows="2" :placeholder="__('projects.file_remarks')">{{ old('remarks') }}</x-ui.odoo-form-ui>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('projects.cancel') }}</button>
            <button type="submit" class="btn btn-primary">
                <i class="feather-upload me-1"></i>{{ __('projects.upload_document') }}
            </button>
        </div>
    </form>
</x-ui.modal>
