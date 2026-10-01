@php
    $docSearch = trim((string) request('search', ''));
    $docCategoryFilter = (string) request('category', '');

    $hasActiveDocFilters = $docSearch !== '' || $docCategoryFilter !== '';

    $filteredDocs = $documents->filter(function ($doc) use ($docSearch, $docCategoryFilter) {
        if ($docSearch !== '') {
            $haystack = strtolower($doc->title . ' ' . $doc->file_name . ' ' . $doc->remarks);
            if (!str_contains($haystack, strtolower($docSearch))) {
                return false;
            }
        }

        if ($docCategoryFilter !== '' && $doc->category !== $docCategoryFilter) {
            return false;
        }

        return true;
    })->values();

    $docPage = (int) request('doc_page', 1);
    $docsPerPage = 10;
    $totalFilteredDocs = $filteredDocs->count();
    $totalDocPages = (int) ceil($totalFilteredDocs / $docsPerPage);
    $paginatedDocs = $filteredDocs->slice(($docPage - 1) * $docsPerPage, $docsPerPage);
@endphp

{{-- Toolbar --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <form method="GET" action="{{ route('projects.show', $project) }}" class="d-flex flex-wrap align-items-center gap-2">
        <input type="hidden" name="tab" value="documents">
        <div style="min-width: 220px;">
            <input type="text" name="search" class="form-control form-control-sm" value="{{ $docSearch }}"
                   placeholder="{{ __('projects.search_placeholder') }}">
        </div>

        <button type="submit" class="btn btn-primary btn-sm">
            <i class="feather-search me-1"></i>{{ __('ui.search') }}
        </button>

        <x-ui.filter :label="__('ui.filter')" offset="0, 5">
            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i>{{ __('projects.filter_options') }}</h6>

            <div class="mb-3">
                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('projects.category') }}</label>
                <x-ui.odoo-form-ui type="select" name="category">
                    <option value="">{{ __('projects.all_categories') }}</option>
                    @foreach (\App\Domains\Projects\Models\ProjectDocument::CATEGORIES as $cat)
                        <option value="{{ $cat }}" @selected($docCategoryFilter === $cat)>
                            {{ __('projects.document_categories.' . $cat) }}
                        </option>
                    @endforeach
                </x-ui.odoo-form-ui>
            </div>

            <div class="d-flex gap-2 justify-content-end mt-4">
                <a href="{{ route('projects.show', ['project' => $project, 'tab' => 'documents']) }}" class="btn btn-sm btn-light border">{{ __('projects.reset') }}</a>
                <button type="submit" class="btn btn-sm btn-primary">{{ __('projects.apply_filters') }}</button>
            </div>
        </x-ui.filter>
    </form>

    @if ($canUploadDocuments)
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
            <i class="feather-upload me-1"></i>{{ __('projects.upload_document') }}
        </button>
    @endif
</div>

{{-- Documents Table --}}
<div class="border rounded bg-white">
    <x-ui.odoo-form-ui type="table" tableClass="table table-hover align-middle mb-0">
        <thead>
            <tr class="text-muted fs-11 text-uppercase border-bottom">
                <th>{{ __('projects.file_name') }}</th>
                <th style="width: 150px;">{{ __('projects.category') }}</th>
                <th style="width: 140px;">{{ __('projects.attached_to') }}</th>
                <th style="width: 100px;">{{ __('projects.file_size') }}</th>
                <th style="width: 150px;">{{ __('projects.uploaded_by') }}</th>
                <th style="width: 120px;">{{ __('projects.uploaded_on') }}</th>
                <th style="width: 90px;" class="text-end">{{ __('projects.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($paginatedDocs as $doc)
                @php
                    $catBadgeClass = match($doc->category) {
                        'sow' => 'badge bg-primary-subtle text-primary border border-primary-subtle',
                        'architecture' => 'badge bg-info-subtle text-info border border-info-subtle',
                        'qa' => 'badge bg-warning-subtle text-warning border border-warning-subtle',
                        'meeting_notes' => 'badge bg-success-subtle text-success border border-success-subtle',
                        'attachment' => 'badge bg-secondary-subtle text-secondary border',
                        default => 'badge bg-secondary-subtle text-secondary',
                    };
                @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-4 text-muted"><i class="{{ $doc->file_icon }}"></i></span>
                            <div>
                                <span class="fw-semibold text-dark d-block fs-13">{{ $doc->title }}</span>
                                <span class="fs-11 text-muted">{{ $doc->file_name }}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="{{ $catBadgeClass }} fs-11">
                            {{ __('projects.document_categories.' . $doc->category) }}
                        </span>
                    </td>
                    <td>
                        @if ($doc->attachable_type && $doc->attachable)
                            @if ($doc->attachable instanceof \App\Domains\Projects\Models\Task)
                                <a href="{{ route('projects.tasks.show', [$project, $doc->attachable]) }}" class="fs-12 text-primary text-decoration-none">
                                    <i class="feather-check-square me-1"></i>{{ $doc->attachable->task_number }}
                                </a>
                            @elseif ($doc->attachable instanceof \App\Domains\Projects\Models\Issue)
                                <a href="{{ route('projects.issues.show', [$project, $doc->attachable]) }}" class="fs-12 text-primary text-decoration-none">
                                    <i class="feather-alert-circle me-1"></i>{{ $doc->attachable->issue_code }}
                                </a>
                            @else
                                <span class="fs-12 text-muted">{{ class_basename($doc->attachable_type) }}</span>
                            @endif
                        @else
                            <span class="badge bg-light text-muted border fs-11">{{ __('projects.title') }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="fs-12 text-muted font-monospace">{{ $doc->human_file_size }}</span>
                    </td>
                    <td>
                        @if ($doc->uploader)
                            <div class="d-flex align-items-center gap-1">
                                <span class="avatar-circle-sm bg-primary-subtle text-primary fw-bold" style="width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 10px;">
                                    {{ strtoupper(substr($doc->uploader->name, 0, 2)) }}
                                </span>
                                <span class="fs-12 text-dark">{{ $doc->uploader->name }}</span>
                            </div>
                        @else
                            <span class="text-muted fs-12">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="fs-12 text-muted">{{ $doc->created_at?->format('Y-m-d') ?: '—' }}</span>
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
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
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="feather-folder fs-2 d-block mb-2 text-muted"></i>
                        <div class="fw-semibold">{{ __('projects.no_documents_found') }}</div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.odoo-form-ui>

    @if ($totalDocPages > 1)
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <span class="fs-12 text-muted">
                {{ __('projects.showing_entries', ['first' => (($docPage - 1) * $docsPerPage) + 1, 'last' => min($docPage * $docsPerPage, $totalFilteredDocs), 'total' => $totalFilteredDocs]) }}
            </span>
            <div class="d-flex gap-1">
                @if ($docPage > 1)
                    <a href="{{ route('projects.show', array_merge(request()->query(), ['project' => $project, 'tab' => 'documents', 'doc_page' => $docPage - 1])) }}" class="btn btn-sm btn-light border">
                        &laquo;
                    </a>
                @endif
                <span class="btn btn-sm btn-light border disabled">{{ $docPage }} / {{ $totalDocPages }}</span>
                @if ($docPage < $totalDocPages)
                    <a href="{{ route('projects.show', array_merge(request()->query(), ['project' => $project, 'tab' => 'documents', 'doc_page' => $docPage + 1])) }}" class="btn btn-sm btn-light border">
                        &raquo;
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
