@extends('layouts.duralux')

@section('title', 'Initiate CAPA | SaaS ERP')
@section('page-title', 'Create CAPA Record')
@section('breadcrumb', 'New CAPA')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm" style="max-width: 600px;">
        <h5 class="fw-bold text-dark mb-4">Initiate CAPA</h5>

        <form method="POST" action="{{ route('production.capas.store') }}">
            @csrf
            
            <div class="mb-3">
                <label class="form-label fw-bold">Select Linked NCR (Optional)</label>
                <select name="ncr_id" class="form-select">
                    <option value="">None / General CAPA</option>
                    @foreach($ncrs as $ncr)
                        <option value="{{ $ncr->id }}">{{ $ncr->ncr_number }} - {{ Str::limit($ncr->description, 40) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Action Owner</label>
                <select name="action_owner_id" class="form-select" required>
                    <option value="">Select Assignee</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Corrective Action Plan</label>
                <textarea name="corrective_action" class="form-control" rows="3" placeholder="Describe corrective measures..." required></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Preventive Action Plan</label>
                <textarea name="preventive_action" class="form-control" rows="3" placeholder="Describe preventive measures..."></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Target Closure Date</label>
                <input type="date" name="target_date" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Initiate CAPA Action</button>
        </form>
    </div>
@endsection
