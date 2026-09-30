<div class="card border rounded-3 mb-4 shadow-none">
    <div class="card-header bg-light py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
        <span class="fw-bold fs-12 text-uppercase text-muted"><i class="feather-paperclip me-1 text-primary"></i>{{ __('projects.documents') }}</span>
        @can('create', [\App\Domains\Projects\Models\ProjectDocument::class, $project])
            <button type="button" class="btn btn-sm btn-primary py-0 px-2 fs-11" data-bs-toggle="modal" data-bs-target="#uploadTaskDocumentModal">
                <i class="feather-upload me-1"></i>{{ __('projects.upload_document') }}
            </button>
        @endcan
    </div>
    <div class="card-body p-0">
        @php
            $taskDocs = $task->documents;
        @endphp
        @if ($taskDocs && $taskDocs->isNotEmpty())
            <ul class="list-group list-group-flush">
                @foreach ($taskDocs as $doc)
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="{{ $doc->file_icon }} fs-4 text-muted"></i>
                            <div>
                                <span class="fw-semibold text-dark fs-13 d-block">{{ $doc->title }}</span>
                                <span class="fs-11 text-muted">{{ $doc->file_name }} ({{ $doc->human_file_size }})</span>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="{{ route('projects.documents.preview', [$project, $doc]) }}" target="_blank" class="btn btn-sm btn-icon btn-light" title="{{ __('projects.preview') }}">
                                <i class="feather-eye"></i>
                            </a>
                            <a href="{{ route('projects.documents.download', [$project, $doc]) }}" class="btn btn-sm btn-icon btn-light" title="{{ __('projects.download') }}">
                                <i class="feather-download"></i>
                            </a>
                            @can('delete', $doc)
                                <form method="POST" action="{{ route('projects.documents.destroy', [$project, $doc]) }}" onsubmit="return confirm('{{ __('projects.confirm_delete_document') }}')" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-icon btn-light text-danger" title="{{ __('projects.delete') }}">
                                        <i class="feather-trash-2"></i>
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="p-3 text-center text-muted fs-12">
                {{ __('projects.no_documents_found') }}
            </div>
        @endif
    </div>
</div>

{{-- Upload Task Document Modal --}}
<x-ui.modal id="uploadTaskDocumentModal" :title="__('projects.upload_document')" size="md" :showFooter="false">
    <form method="POST" action="{{ route('projects.documents.store', $project) }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="attachable_type" value="task">
        <input type="hidden" name="attachable_id" value="{{ $task->id }}">

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.category') }} <span class="text-danger">*</span></label>
                <x-ui.odoo-form-ui type="select" name="category" required>
                    @foreach (\App\Domains\Projects\Models\ProjectDocument::CATEGORIES as $cat)
                        <option value="{{ $cat }}" @selected($cat === 'attachment')>
                            {{ __('projects.document_categories.' . $cat) }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.file_name') }} ({{ __('ui.optional') }})</label>
                <x-ui.odoo-form-ui type="input" name="title" :placeholder="__('projects.file_name')" />
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.document') }} <span class="text-danger">*</span></label>
                <input type="file" name="file" class="form-control form-control-sm" required>
                <div class="form-text fs-11 text-muted">{{ __('projects.max_file_size_hint') }}</div>
            </div>

            <div class="col-12">
                <label class="form-label fw-bold fs-12 text-uppercase text-muted">{{ __('projects.file_remarks') }}</label>
                <x-ui.odoo-form-ui type="textarea" name="remarks" rows="2" :placeholder="__('projects.file_remarks')" />
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
