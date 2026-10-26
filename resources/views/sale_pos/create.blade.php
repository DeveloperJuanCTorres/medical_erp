@extends('layouts.app')

@section('title', __('sale.pos_sale'))

@section('content')
<section class="content no-print pos-modern-screen">
    <input type="hidden" id="amount_rounding_method" value="{{$pos_settings['amount_rounding_method'] ?? ''}}">
    @if(!empty($pos_settings['allow_overselling']))
        <input type="hidden" id="is_overselling_allowed">
    @endif
    @if(session('business.enable_rp') == 1)
        <input type="hidden" id="reward_point_enabled">
    @endif
    @php
        $is_discount_enabled = $pos_settings['disable_discount'] != 1 ? true : false;
        $is_rp_enabled = session('business.enable_rp') == 1 ? true : false;
    @endphp

    {!! Form::open(['url' => action([\App\Http\Controllers\SellPosController::class, 'store']), 'method' => 'post', 'id' => 'add_pos_sell_form']) !!}

    <div class="pos-modern-topline">
        <div class="pos-modern-heading">
            <span class="pos-modern-heading-icon"><i class="fas fa-cash-register"></i></span>
            <span><strong>Punto de venta</strong><small>Registrar nueva venta</small></span>
        </div>
        <span class="pos-modern-topline-tag"><i class="fas fa-circle"></i> Caja de ventas</span>
    </div>

    <div class="row pos-modern-layout mb-12">
        <div class="col-md-12">
            <div class="row pos-modern-columns pos-modern-layout-reversed">
                <div class="@if(empty($pos_settings['hide_product_suggestion'])) col-md-7 @else col-md-10 col-md-offset-1 @endif pos-modern-main-column">
                    <div class="box box-solid mb-12 pos-modern-sale-panel">
                        <div class="box-body pb-0">
                            {!! Form::hidden('location_id', $default_location->id ?? null, ['id' => 'location_id', 'data-receipt_printer_type' => !empty($default_location->receipt_printer_type) ? $default_location->receipt_printer_type : 'browser', 'data-default_payment_accounts' => $default_location->default_payment_accounts ?? '']); !!}
                            {!! Form::hidden('sub_type', isset($sub_type) ? $sub_type : null) !!}
                            <input type="hidden" id="item_addition_method" value="{{$business_details->item_addition_method}}">

                            <div class="pos-modern-panel-heading"><i class="fas fa-shopping-basket"></i> Detalle de venta</div>
                            @include('sale_pos.partials.pos_form')
                            @include('sale_pos.partials.pos_form_totals')
                            @include('sale_pos.partials.payment_modal')

                            @if(empty($pos_settings['disable_suspend']))
                                @include('sale_pos.partials.suspend_note_modal')
                            @endif
                            @if(empty($pos_settings['disable_recurring_invoice']))
                                @include('sale_pos.partials.recurring_invoice_modal')
                            @endif
                        </div>
                    </div>
                </div>
                @if(empty($pos_settings['hide_product_suggestion']))
                    <div class="col-md-5 pos-modern-catalog-column">
                        <div class="pos-modern-catalog-panel">
                            <div class="pos-modern-panel-heading"><i class="fas fa-th-large"></i> Catálogo de productos</div>
                            @include('sale_pos.partials.pos_sidebar')
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="pos-modern-actions-wrap">
        @include('sale_pos.partials.pos_form_actions')
    </div>
    {!! Form::close() !!}
</section>

<section class="invoice print_section" id="receipt_section"></section>
<div class="modal fade contact_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    @include('contact.create', ['quick_add' => true])
</div>
@if(empty($pos_settings['hide_product_suggestion']) && isMobile())
    @include('sale_pos.partials.mobile_product_suggestions')
@endif
<div class="modal fade register_details_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade close_register_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
<div class="modal fade quick_add_product_modal" tabindex="-1" role="dialog" aria-labelledby="modalTitle"></div>
<div class="modal fade" id="expense_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
@include('sale_pos.partials.configure_search_modal')
@include('sale_pos.partials.recent_transactions_modal')
@include('sale_pos.partials.weighing_scale_modal')
@stop

@section('css')
    @if(!empty($pos_module_data))
        @foreach($pos_module_data as $key => $value)
            @if(!empty($value['module_css_path']))
                @includeIf($value['module_css_path'])
            @endif
        @endforeach
    @endif
    {{-- FASE 1: DISEÑO GENERAL --}}
    <link rel="stylesheet" href="{{ asset('css/pos-modern-pro.css?v=1') }}">

    {{-- FASE 2: CATÁLOGO --}}
    <link rel="stylesheet" href="{{ asset('css/pos-modern-phase2.css?v=1') }}">

    {{-- FASE 3: PAGOS --}}
    <link rel="stylesheet" href="{{ asset('css/pos-modern-phase3-payments.css?v=1') }}">

    {{-- FASE 4: ACCIONES --}}
    <link rel="stylesheet" href="{{ asset('css/pos-modern-phase4-actions.css?v=1') }}">

    {{-- FASE 5: DISTRIBUCIÓN --}}
    <link rel="stylesheet" href="{{ asset('css/pos-modern-phase5-layout.css?v=3') }}">

    {{-- FASE 6: CARRITO --}}
    <link rel="stylesheet" href="{{ asset('css/pos-modern-phase6-cart.css?v=1') }}">

    {{-- FASE 7: RESUMEN --}}
    <link rel="stylesheet" href="{{ asset('css/pos-modern-phase7-totals.css?v=1') }}">

    {{-- FASE 8: AJUSTES FINALES DEL CARRITO --}}
    <link rel="stylesheet" href="{{ asset('css/pos-modern-phase8-cart.css?v=12') }}">

@stop

@section('javascript')
    <script src="{{ asset('js/pos.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/printer.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/opening_stock.js?v=' . $asset_v) }}"></script>
    @include('sale_pos.partials.keyboard_shortcuts')
    @if(in_array('tables', $enabled_modules) || in_array('modifiers', $enabled_modules) || in_array('service_staff', $enabled_modules))
        <script src="{{ asset('js/restaurant.js?v=' . $asset_v) }}"></script>
    @endif
    @if(!empty($pos_module_data))
        @foreach($pos_module_data as $key => $value)
            @if(!empty($value['module_js_path']))
                @includeIf($value['module_js_path'], ['view_data' => $value['view_data']])
            @endif
        @endforeach
    @endif	
	
	
	<script>
		document.addEventListener('error', function (event) {
			var img = event.target;

			if (
				img.tagName !== 'IMG' ||
				!img.closest('#product_list_body')
			) {
				return;
			}

			var placeholder = @json(asset('img/product-placeholder.png'));

			if (img.src !== placeholder) {
				img.src = placeholder;
			}
		}, true);
	</script>


	
	<script>
		$(function () {
			function organizarBuscadorPOS() {
				var $buscador = $('.pos-modern-product-search-field');
				var $destino = $('#pos-modern-catalog-search');

				if ($buscador.length && $destino.length) {
					$buscador
						.removeClass('col-md-12')
						.addClass('pos-modern-search-moved');

					$destino.append($buscador);
				}
			}

			organizarBuscadorPOS();
		});
	</script>



@endsection
