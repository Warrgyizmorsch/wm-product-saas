@extends('layouts.duralux')

@section('title', 'MES Barcode Scanner Simulator | SaaS ERP')
@section('page-title', 'MES Barcode Scanner Simulator')
@section('breadcrumb', 'Scanner')

@section('content')
    <div class="container-fluid py-2">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <div class="row g-4">
            <div class="col-lg-6 mx-auto">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 text-center">
                        <i class="feather-camera text-primary fs-48 mb-3"></i>
                        <h4 class="fw-bold mb-2">Scan Device Simulator</h4>
                        <p class="text-muted mb-4">Input raw scanned entity barcode/QR code token string (e.g. ORD-00000001) to simulate industrial tablet scan events.</p>

                        <form method="POST" action="{{ route('production.mes.scanner.scan') }}">
                            @csrf
                            <div class="mb-4">
                                <input type="text" name="code" class="form-control form-control-lg text-center fw-bold font-monospace" placeholder="e.g. ORD-00000001, BAT-00000001..." required autofocus>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100" style="min-height: 48px;">
                                <i class="feather-send me-1"></i> Send Scan Event
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Mock Scan Helpers --}}
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-transparent border-bottom pt-4">
                        <h6 class="fw-bold text-dark mb-0">Quick Scan Event Templates</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="list-group list-group-flush">
                            {{-- We can find some active orders and batches to mock --}}
                            @php
                                $orders = \App\Domains\Production\Models\ProductionOrder::take(3)->get();
                                $batches = \App\Domains\Production\Models\ProductionBatch::take(3)->get();
                                $serials = \App\Domains\Production\Models\ProductionSerialNumber::take(3)->get();
                            @endphp

                            @if($orders->isNotEmpty())
                                <div class="fs-11 text-muted uppercase font-semibold mb-2 mt-2">Production Orders</div>
                                @foreach($orders as $o)
                                    @php $oCode = "ORD-" . str_pad($o->id, 8, '0', STR_PAD_LEFT); @endphp
                                    <button class="list-group-item list-group-item-action font-monospace fs-13 d-flex justify-content-between align-items-center" onclick="simulateScan('{{ $oCode }}')">
                                        <span>{{ $oCode }} <small class="text-muted">({{ $o->order_number }})</small></span>
                                        <i class="feather-arrow-right"></i>
                                    </button>
                                @endforeach
                            @endif

                            @if($batches->isNotEmpty())
                                <div class="fs-11 text-muted uppercase font-semibold mb-2 mt-3">Production Batches</div>
                                @foreach($batches as $b)
                                    @php $bCode = "BAT-" . str_pad($b->id, 8, '0', STR_PAD_LEFT); @endphp
                                    <button class="list-group-item list-group-item-action font-monospace fs-13 d-flex justify-content-between align-items-center" onclick="simulateScan('{{ $bCode }}')">
                                        <span>{{ $bCode }} <small class="text-muted">({{ $b->batch_number }})</small></span>
                                        <i class="feather-arrow-right"></i>
                                    </button>
                                @endforeach
                            @endif

                            @if($serials->isNotEmpty())
                                <div class="fs-11 text-muted uppercase font-semibold mb-2 mt-3">Serial Numbers</div>
                                @foreach($serials as $s)
                                    @php $sCode = "SER-" . str_pad($s->id, 8, '0', STR_PAD_LEFT); @endphp
                                    <button class="list-group-item list-group-item-action font-monospace fs-13 d-flex justify-content-between align-items-center" onclick="simulateScan('{{ $sCode }}')">
                                        <span>{{ $sCode }} <small class="text-muted">({{ $s->serial_number }})</small></span>
                                        <i class="feather-arrow-right"></i>
                                    </button>
                                @endforeach
                            @endif

                            @if($orders->isEmpty() && $batches->isEmpty() && $serials->isEmpty())
                                <div class="text-center py-4 text-muted fs-12">
                                    No records available in database yet to generate scanning templates.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function simulateScan(code) {
            let input = document.querySelector('input[name="code"]');
            if (input) {
                input.value = code;
                input.form.submit();
            }
        }
    </script>
@endsection
