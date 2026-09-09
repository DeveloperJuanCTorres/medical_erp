@extends('layouts.app')
@section('title', __( 'lang_v1.all_sales'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <!-- <h1>lang( 'sale.sells') -->
    <h1>Panel SUNAT
    </h1>
</section>

<!-- Main content -->
<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        @include('sell.partials.sell_list_filters_sunat')
        @if(!empty($sources))
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('sell_list_filter_source',  __('lang_v1.sources') . ':') !!}

                    {!! Form::select('sell_list_filter_source', $sources, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all') ]); !!}
                </div>
            </div>
        @endif
    @endcomponent
    <!-- component('components.widget', ['class' => 'box-primary', 'title' => __( 'lang_v1.all_sales')]) -->
    @component('components.widget', ['class' => 'box-primary', 'title' => 'Facturación Electrónica'])
       
        @if(auth()->user()->can('direct_sell.view') ||  auth()->user()->can('view_own_sell_only') ||  auth()->user()->can('view_commission_agent_sell'))
        @php
            $custom_labels = json_decode(session('business.custom_labels'), true);
         @endphp
            <table class="table table-bordered table-striped ajax_view" id="sell_table">
                <thead>
                    <tr>
                        <!-- <th>lang('messages.action')</th> -->
                        <th>Tipo</th>
                        <th>@lang('messages.date')</th> 
                        <th>@lang('sale.invoice_no')</th>
                        <th>@lang('sale.customer_name')</th>                        
                        <th>@lang('sale.total_amount')</th>
                        <th>Estado</th>
                        <th>Observación SUNAT</th>
                        <th>PDF</th>
                        <th>XML</th>
                        <th>CDR</th>
                        <th>SUNAT</th>                        
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        @endif
    @endcomponent
</section>
<!-- /.content -->
<div class="modal fade payment_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

<div class="modal fade edit_payment_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

<!-- This will be printed -->
<!-- <section class="invoice print_section" id="receipt_section">
</section> -->

@stop

@section('javascript')
<!-- <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> -->
<script type="text/javascript">
$(document).ready( function(){
    //Date range as a button
    $('#sell_list_filter_date_range').daterangepicker(
        dateRangeSettings,
        function (start, end) {
            $('#sell_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            sell_table.ajax.reload();
        }
    );
    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
        $('#sell_list_filter_date_range').val('');
        sell_table.ajax.reload();
    });

    sell_table = $('#sell_table').DataTable({
        processing: true,
        serverSide: true,
        aaSorting: [[1, 'desc']],
        "ajax": {
            "url": "/panelSunat",
            "data": function ( d ) {
                if($('#sell_list_filter_date_range').val()) {
                    var start = $('#sell_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                    var end = $('#sell_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    d.start_date = start;
                    d.end_date = end;
                }
                d.is_direct_sale = 1;

                d.location_id = $('#sell_list_filter_location_id').val();
                d.customer_id = $('#sell_list_filter_customer_id').val();
                d.payment_status = $('#sell_list_filter_payment_status').val();
                d.created_by = $('#created_by').val();
                d.sales_cmsn_agnt = $('#sales_cmsn_agnt').val();
                d.service_staffs = $('#service_staffs').val();

                if($('#shipping_status').length) {
                    d.shipping_status = $('#shipping_status').val();
                }

                if($('#sell_list_filter_source').length) {
                    d.source = $('#sell_list_filter_source').val();
                }

                if($('#only_subscriptions').is(':checked')) {
                    d.only_subscriptions = 1;
                }

                d = __datatable_ajax_callback(d);
            }
        },
        scrollY:        "75vh",
        scrollX:        true,
        scrollCollapse: true,
        columns: [
            { data: 'type', name: 'type'},
            { data: 'transaction_date', name: 'transaction_date'  },
            { data: 'invoice_no', name: 'invoice_no'},
            { data: 'conatct_name', name: 'conatct_name'},            
            { data: 'final_total', name: 'final_total'},
            { data: 'estado_sunat', name: 'estado_sunat'},
            { data: 'observacion', name: 'observacion'},
            { data: 'pdf', name: 'pdf'},
            { data: 'xml', name: 'xml'},
            { data: 'cdr', name: 'cdr'},
            { data: 'sunat', name: 'sunat' },            
        ],
        "fnDrawCallback": function (oSettings) {
            __currency_convert_recursively($('#sell_table'));
        },        
        createdRow: function( row, data, dataIndex ) {
            $( row ).find('td:eq(6)').attr('class', 'clickable_td');
        }
    });

    $(document).on('change', '#sell_list_filter_location_id, #sell_list_filter_customer_id, #sell_list_filter_payment_status, #created_by, #sales_cmsn_agnt, #service_staffs, #shipping_status, #sell_list_filter_source',  function() {
        sell_table.ajax.reload();
    });

    $('#only_subscriptions').on('ifChanged', function(event){
        sell_table.ajax.reload();
    });

    $(document).on('click', 'button.envio_sunat_button', function() {
        var type = $(this).attr("type-id");
        if (type == 'sell') {
            swal({
                title: 'Envío de Factura electrónica',
                text: '¿Estás seguro de enviar este documento a sunat?',
                icon: "success",
                buttons: true,
            }).then((willDelete) => {
                if (willDelete) {
                    var data = $(this).attr("data-id");
                    $.ajax({
                        method: "POST",
                        url: "/enviarsunat",
                        dataType: "json",
                        data: {id: data},
                        success: function(result){
                            if(result.status == true){
                                toastr.success(result.msg);
                                sell_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        }
                    });
                }
            });
        }
        if (type == 'sell_return')
        {
            const select = document.createElement("select");
            const opt1 = document.createElement("option");
            const opt2 = document.createElement("option");
            const opt3 = document.createElement("option");
            const opt4 = document.createElement("option");
            const opt5 = document.createElement("option");
            const opt6 = document.createElement("option");
            const opt7 = document.createElement("option");
            const opt8 = document.createElement("option");
            const opt9 = document.createElement("option");
            const opt10 = document.createElement("option");
            const opt11 = document.createElement("option");
            const opt12 = document.createElement("option");
            const opt13 = document.createElement("option");
            const placeholder = document.createElement("placeholder");

            

            opt1.value = "1";
            opt1.text = "ANULACIÓN DE LA OPERACIÓN";
            opt2.value = "2";
            opt2.text = "ANULACIÓN POR ERROR EN EL RUC";
            opt3.value = "3";
            opt3.text = "CORRECCIÓN POR ERROR EN LA DESCRIPCIÓN";
            opt4.value = "4";
            opt4.text = "DESCUENTO GLOBAL";
            opt5.value = "5";
            opt5.text = "DESCUENTO POR ÍTEM";
            opt6.value = "6";
            opt6.text = "DEVOLUCIÓN TOTAL";
            opt7.value = "7";
            opt7.text = "DEVOLUCIÓN POR ÍTEM";
            opt8.value = "8";
            opt8.text = "BONIFICACIÓN";
            opt9.value = "9";
            opt9.text = "DISMINUCIÓN EN EL VALOR";
            opt10.value = "10";
            opt10.text = "OTROS CONCEPTOS";
            opt11.value = "11";
            opt11.text = "AJUSTES AFECTOS AL IVAP";
            opt12.value = "12";
            opt12.text = "AJUSTES DE OPERACIONES DE EXPORTACIÓN";
            opt13.value = "13";
            opt13.text = "AJUSTES - MONTOS Y/O FECHAS DE PAGO";

            select.className = "form-control";

            select.add(opt1, null);
            select.add(opt2, null);
            select.add(opt3, null);
            select.add(opt4, null);
            select.add(opt5, null);
            select.add(opt6, null);
            select.add(opt7, null);
            select.add(opt8, null);
            select.add(opt9, null);
            select.add(opt10, null);
            select.add(opt11, null);
            select.add(opt12, null);
            select.add(opt13, null);
            swal({
                title: 'Envío de Nota de Crédito',
                text: '¿Estás seguro de enviar este documento a sunat?',
                icon: "success",
                content : select,
                buttons: true,
            }).then((willDelete) => {
                if (willDelete) {
                    var data = $(this).attr("data-id");
                    console.log(select.value);
                    $.ajax({
                        method: "POST",
                        url: "/enviarsunat",
                        dataType: "json",
                        data: {id: data,motivo_id: select.value},
                        success: function(result){
                            if(result.status == true){
                                toastr.success(result.msg);
                                sell_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        }
                    });
                }
            });
        }
        
    });

    $(document).on('click', 'button.anulacion_sunat_button', function() {
        swal({
        title: 'Motivo de la anulación',
        inputAttributes: {
            autocapitalize: 'off'
        },    
        content: "input",        
        buttons: true,
        allowOutsideClick: () => !swal.isLoading()
        }).then((willDelete) => {
            if (willDelete) {
                var data = $(this).attr("data-id");
                console.log(willDelete);
                $.ajax({
                    method: "POST",
                    url: "/anulacionSunat",
                    dataType: "json",
                    data: {id: data, motivo: willDelete},
                    success: function(result){
                        if(result.status == true){
                            toastr.success(result.msg);
                            sell_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            }
        });
    });

    $(document).on('click', 'a.nota_credito_a', function() {
        swal({
        title: 'Observación',
        inputAttributes: {
            autocapitalize: 'off'
        },    
        content: "input",        
        buttons: true,
        allowOutsideClick: () => !swal.isLoading()
        }).then((willDelete) => {
            if (willDelete) {
                var data = $(this).attr("data-id");
                $.ajax({
                    method: "POST",
                    url: "/notaCreditoSunat",
                    dataType: "json",
                    data: {id: data, observacion: willDelete},
                    success: function(result){
                        if(result.status == true){
                            toastr.success(result.msg);
                            sell_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            }
        });
    });
    
});

</script>

<script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endsection