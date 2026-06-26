@props([
    'title' => null,
    'searchPlaceholder' => null,
    'headers' => null,
    'rows' => null,
    'striped' => false,
    'hoverable' => true,
    'bordered' => false
])

<div {{ $attributes->class(['table-responsive-container']) }}>
    @if($title || $searchPlaceholder)
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 p-4 pb-0">
            @if($title)
                <h5 class="fw-bold text-dark mb-0 fs-16">{{ $title }}</h5>
            @endif
            @if($searchPlaceholder)
                <div class="d-flex align-items-center bg-light border rounded px-3 py-1" style="min-width: 280px; max-width: 320px;">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input type="text" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ $searchPlaceholder }}" style="box-shadow: none; height: 32px;">
                </div>
            @endif
        </div>
    @endif

    <div class="table-responsive p-4">
        <table class="table {{ $striped ? 'table-striped' : '' }} {{ $hoverable ? 'table-hover' : '' }} {{ $bordered ? 'table-bordered' : '' }} align-middle">
            @if($slot->isNotEmpty())
                {{ $slot }}
            @else
                @if($headers)
                    <thead>
                        <tr>
                            @foreach($headers as $header)
                                <th scope="col">{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                @endif
                @if($rows)
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                @foreach($row as $cell)
                                    <td>{!! $cell !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endif
            @endif
        </table>
    </div>
</div>
