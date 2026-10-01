@extends('layouts.duralux')

@section('title', 'GSTR-2B Reconciliation | SaaS ERP')
@section('page-title', 'GSTR-2B Reconciliation')
@section('breadcrumb', 'Accounting / GST Returns / GSTR-2B Reconciliation')

@section('content')
    @if ($canFile)
        <x-ui.card class="mb-4">
            <x-slot:title>Upload GSTR-2B</x-slot:title>
            <div class="row g-4">
                <div class="col-lg-7">
                    <form method="POST" action="{{ route('accounting.gst-returns.gstr2b.store') }}" enctype="multipart/form-data">
                        @csrf
                        <label for="gstr2bFile" class="form-label fw-semibold fs-13 text-dark mb-2">GSTR-2B JSON file <span class="text-danger">*</span></label>
                        <div class="d-flex align-items-start gap-2 gstr2b-upload">
                            <div class="flex-grow-1">
                                <x-ui.input type="file" name="file" id="gstr2bFile" accept=".json,application/json" required
                                    helperText="Uploading the same month again replaces the earlier upload." />
                            </div>
                            <x-ui.button type="submit" variant="primary" icon="feather-upload">Upload &amp; match</x-ui.button>
                        </div>
                        @error('file')
                            <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                        @enderror
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="gstr2b-steps fs-12 text-muted">
                        <div class="fw-semibold text-dark mb-2">Where to get the file</div>
                        <ol class="ps-3 mb-0">
                            <li>Log in to <span class="text-dark">gst.gov.in</span> → Services → Returns → Returns Dashboard.</li>
                            <li>Pick the financial year and month, then open <span class="text-dark">GSTR-2B → Download</span>.</li>
                            <li>Choose <span class="text-dark">Generate JSON file to download</span>, unzip it, and upload the .json here.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <x-slot:title>Uploaded periods</x-slot:title>

        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Period</th>
                    <th>GSTIN</th>
                    <th class="text-end">Documents</th>
                    <th>Result</th>
                    <th class="text-end">ITC as per 2B</th>
                    <th>Uploaded</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($imports as $import)
                    @php $s = $import->summary ?? []; @endphp
                    <tr>
                        <td class="ps-4"><a href="{{ route('accounting.gst-returns.gstr2b.show', $import->id) }}" class="fw-semibold">{{ $import->periodLabel() }}</a></td>
                        <td class="text-muted">{{ $import->gstin ?? '—' }}</td>
                        <td class="text-end">{{ $import->line_count }}</td>
                        <td>
                            <x-ui.status-pill-group :items="[
                                ['label' => 'Matched', 'value' => $s['matched']['count'] ?? 0, 'variant' => 'success'],
                                ['label' => 'Mismatch', 'value' => $s['mismatch']['count'] ?? 0, 'variant' => 'warning'],
                                ['label' => 'Not in books', 'value' => $s['missing_in_books']['count'] ?? 0, 'variant' => 'danger'],
                                ['label' => 'Not in 2B', 'value' => $s['books_only']['count'] ?? 0, 'variant' => 'primary'],
                            ]" />
                        </td>
                        <td class="text-end fw-semibold">{{ number_format((float) ($s['itc_as_per_2b'] ?? 0), 2) }}</td>
                        <td class="text-muted fs-12" style="white-space: nowrap;">
                            {{ $import->created_at?->format('d M Y, H:i') }}
                            @if ($import->importer)
                                <div>{{ $import->importer->name }}</div>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <x-ui.button href="{{ route('accounting.gst-returns.gstr2b.show', $import->id) }}" variant="light-brand" size="sm" icon="feather-eye">Open</x-ui.button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No GSTR-2B uploaded yet.@if ($canFile) Upload the JSON from the GST portal above to start.@endif</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <x-ui.pagination
            :currentPage="$imports->currentPage()"
            :totalPages="$imports->lastPage()"
            :totalResults="$imports->total()"
            :perPage="$imports->perPage()" />
    </x-ui.card>
@endsection

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 8px 10px !important;
            font-size: 12px !important;
            vertical-align: middle;
        }

        .gstr2b-upload .mb-3 { margin-bottom: 0 !important; }
        .gstr2b-steps {
            background: color-mix(in srgb, var(--bs-primary) 6%, #fff);
            border: 1px solid color-mix(in srgb, var(--bs-primary) 18%, #fff);
            border-radius: 8px;
            padding: 12px 14px;
        }
        .gstr2b-steps li + li { margin-top: 4px; }
    </style>
@endpush
