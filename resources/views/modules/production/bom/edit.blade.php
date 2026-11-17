@extends('layouts.duralux')

@section('title', __('production.edit_bom') . ' | SaaS ERP')
@section('page-title', __('production.edit_bill_of_materials'))
@section('breadcrumb', __('production.edit_bom'))

@section('content')
    @include('modules.production.bom.partials._form', [
        'mode'                 => 'edit',
        'bom'                  => $bom,
        'copyFromBom'          => null,
        'copyMode'             => null,
        'suggestedVersion'     => null,
        'selectedProductId'    => $bom->product_id,
        'selectedRoutingId'    => $bom->routing_id,
        'copyItems'            => null,
        'products'             => $products,
        'materials'            => $materials,
        'uoms'                 => $uoms,
        'routings'             => $routings,
        'routingAdvisoriesMap' => $routingAdvisoriesMap ?? [],
    ])
@endsection
