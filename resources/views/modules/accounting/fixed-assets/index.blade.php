@extends('layouts.duralux')

@section('title', 'Fixed Asset Register | SaaS ERP')
@section('page-title', 'Fixed Asset Register')
@section('breadcrumb', 'Accounting / Fixed Assets / Register')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('accounting.fixed-assets.categories.index') }}" variant="light" icon="feather-sliders" class="border">
            Asset Categories
        </x-ui.button>
        <x-ui.button href="{{ route('accounting.fixed-assets.register') }}" variant="primary" icon="feather-plus">
            Register Asset
        </x-ui.button>
    </div>
    <x-ui.filter label="Filters">
        <form method="GET">
            <x-ui.select label="Category" name="category_id" :selected="$filters['category_id'] ?? ''" :options="
                ['' => 'All'] + $categories->pluck('name', 'id')->all()
            " />
            <x-ui.select label="Status" name="status" :selected="$filters['status'] ?? ''" :options="[
                '' => 'All',
                'available' => 'Available (not capitalized)',
                'active' => 'Active',
                'idle' => 'Idle',
                'under_maintenance' => 'Under Maintenance',
                'fully_depreciated' => 'Fully Depreciated',
                'sold' => 'Sold',
                'scrapped' => 'Scrapped',
                'written_off' => 'Written Off',
                'lost' => 'Lost',
            ]" />
            <x-ui.button type="submit" variant="primary" size="sm" class="w-100">Apply</x-ui.button>
        </form>
    </x-ui.filter>
@endsection

@section('content')
    <x-ui.card bodyClass="p-0">
        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
            <form method="GET" class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 280px; max-width: 360px;">
                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control border-0 bg-transparent p-0 fs-13"
                       placeholder="Search asset code, name, serial..." style="box-shadow: none; height: 32px;">
                @if (!empty($filters['status']))
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                @endif
                @if (!empty($filters['category_id']))
                    <input type="hidden" name="category_id" value="{{ $filters['category_id'] }}">
                @endif
            </form>
        </div>

        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Asset Code</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th class="text-end">Capitalization Cost</th>
                    <th class="text-end">Accum. Depreciation</th>
                    <th class="text-end">Book Value</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($assets as $asset)
                    <tr>
                        <td class="ps-4 fw-bold font-monospace">{{ $asset->asset_code }}</td>
                        <td>{{ $asset->name }}</td>
                        <td class="text-muted">{{ $asset->category?->name ?? '—' }}</td>
                        <td class="text-end">{{ $asset->capitalization_cost !== null ? number_format($asset->capitalization_cost, 2) : '—' }}</td>
                        <td class="text-end">{{ $asset->capitalization_cost !== null ? number_format($asset->accumulated_depreciation, 2) : '—' }}</td>
                        <td class="text-end fw-semibold">{{ $asset->book_value !== null ? number_format($asset->book_value, 2) : '—' }}</td>
                        <td><x-ui.status-badge :status="$asset->status" /></td>
                        <td class="text-end pe-4">
                            <x-ui.icon-btn href="{{ route('accounting.fixed-assets.show', $asset) }}" variant="soft-primary" icon="feather-eye" title="View" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="feather-box fs-1 mb-2 d-block"></i>
                            No fixed assets found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <x-ui.pagination
            :currentPage="$assets->currentPage()"
            :totalPages="$assets->lastPage()"
            :totalResults="$assets->total()"
            :perPage="$assets->perPage()" />
    </x-ui.card>
@endsection
