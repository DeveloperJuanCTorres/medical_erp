
<div class="row pos_form_totals pos-modern-totals-compact">
    <div class="col-md-12">

        {{-- RESUMEN COMPACTO SIEMPRE VISIBLE --}}
        <div class="pos-modern-totals-bar">

            <table class="table table-condensed pos-modern-summary-table">
				<tbody>
					<tr>
						<td>
							<i class="fas fa-shopping-basket"></i>
							<b>@lang('sale.item'):</b>
							<span class="total_quantity">0</span>
						</td>

						<td class="text-right">
							<b>Subtotal de productos:</b>
							<span class="price_total">0</span>
						</td>
					</tr>
				</tbody>
			</table>

            <details class="pos-modern-sale-adjustments">
                <summary class="pos-modern-adjustments-trigger">
                    <i class="fas fa-sliders-h"></i>
                    Ajustes de venta
                    <i class="fas fa-chevron-down pos-modern-chevron"></i>
                </summary>

                <div class="pos-modern-adjustments-content">

                    {{-- DESCUENTO Y PUNTOS --}}
                    <div class="pos-modern-adjustment-item">

                        @if($is_discount_enabled)
                            <div class="pos-modern-adjustment-label">
                                <strong>
                                    @lang('sale.discount') (-)
                                    @show_tooltip(__('tooltip.sale_discount'))
                                </strong>

                                @if($edit_discount)
                                    <i class="fas fa-edit cursor-pointer"
                                       id="pos-edit-discount"
                                       title="@lang('sale.edit_discount')"
                                       data-toggle="modal"
                                       data-target="#posEditDiscountModal"></i>
                                @endif
                            </div>

                            <span id="total_discount">0</span>
                        @endif

                        @if($is_rp_enabled)
                            <div class="pos-modern-rp-label">
                                {{ session('business.rp_name') }}
                            </div>
                        @endif

                        {{-- Mantener los campos originales --}}
                        <input type="hidden"
                               name="discount_type"
                               id="discount_type"
                               value="@if(empty($edit))percentage@else{{$transaction->discount_type}}@endif"
                               data-default="percentage">

                        <input type="hidden"
                               name="discount_amount"
                               id="discount_amount"
                               value="@if(empty($edit)){{@num_format($business_details->default_sales_discount)}}@else{{@num_format($transaction->discount_amount)}}@endif"
                               data-default="{{$business_details->default_sales_discount}}">

                        <input type="hidden"
                               name="rp_redeemed"
                               id="rp_redeemed"
                               value="@if(empty($edit))0@else{{$transaction->rp_redeemed}}@endif">

                        <input type="hidden"
                               name="rp_redeemed_amount"
                               id="rp_redeemed_amount"
                               value="@if(empty($edit))0@else{{$transaction->rp_redeemed_amount}}@endif">
                    </div>

                    {{-- IMPUESTO DEL PEDIDO --}}
                    <div class="pos-modern-adjustment-item
                        @if($pos_settings['disable_order_tax'] != 0) hide @endif">

                        <div class="pos-modern-adjustment-label">
                            <strong>
                                @lang('sale.order_tax') (+)
                                @show_tooltip(__('tooltip.sale_tax'))
                            </strong>

                            <i class="fas fa-edit cursor-pointer"
                               id="pos-edit-tax"
                               title="@lang('sale.edit_order_tax')"
                               data-toggle="modal"
                               data-target="#posEditOrderTaxModal"></i>
                        </div>

                        <span id="order_tax">
                            @if(empty($edit))
                                0
                            @else
                                {{$transaction->tax_amount}}
                            @endif
                        </span>

                        <input type="hidden"
                               name="tax_rate_id"
                               id="tax_rate_id"
                               value="@if(empty($edit)){{$business_details->default_sales_tax}}@else{{$transaction->tax_id}}@endif"
                               data-default="{{$business_details->default_sales_tax}}">

                        <input type="hidden"
                               name="tax_calculation_amount"
                               id="tax_calculation_amount"
                               value="@if(empty($edit)){{@num_format($business_details->tax_calculation_amount)}}@else{{@num_format($transaction->tax?->amount)}}@endif"
                               data-default="{{$business_details->tax_calculation_amount}}">
                    </div>

                    {{-- TRANSPORTE --}}
                    <div class="pos-modern-adjustment-item">

                        <div class="pos-modern-adjustment-label">
                            <strong>
                                @lang('sale.shipping') (+)
                                @show_tooltip(__('tooltip.shipping'))
                            </strong>

                            <i class="fas fa-edit cursor-pointer"
                               title="@lang('sale.shipping')"
                               data-toggle="modal"
                               data-target="#posShippingModal"></i>
                        </div>

                        <span id="shipping_charges_amount">0</span>

                        <input type="hidden"
                               name="shipping_details"
                               id="shipping_details"
                               value="@if(empty($edit)){{''}}@else{{$transaction->shipping_details}}@endif"
                               data-default="">

                        <input type="hidden"
                               name="shipping_address"
                               id="shipping_address"
                               value="@if(empty($edit)){{''}}@else{{$transaction->shipping_address}}@endif">

                        <input type="hidden"
                               name="shipping_status"
                               id="shipping_status"
                               value="@if(empty($edit)){{''}}@else{{$transaction->shipping_status}}@endif">

                        <input type="hidden"
                               name="delivered_to"
                               id="delivered_to"
                               value="@if(empty($edit)){{''}}@else{{$transaction->delivered_to}}@endif">

                        <input type="hidden"
                               name="shipping_charges"
                               id="shipping_charges"
                               value="@if(empty($edit)){{@num_format(0.00)}}@else{{@num_format($transaction->shipping_charges)}}@endif"
                               data-default="0.00">
                    </div>

                    {{-- CARGO POR EMBALAJE --}}
                    @if(in_array('types_of_service', $enabled_modules))
                        <div class="pos-modern-adjustment-item">

                            <div class="pos-modern-adjustment-label">
                                <strong>
                                    @lang('lang_v1.packing_charge') (+)
                                </strong>

                                <i class="fas fa-edit cursor-pointer service_modal_btn"></i>
                            </div>

                            <span id="packing_charge_text">0</span>
                        </div>
                    @endif

                    {{-- REDONDEO --}}
                    @if(!empty($pos_settings['amount_rounding_method']) && $pos_settings['amount_rounding_method'] > 0)
                        <div class="pos-modern-adjustment-item">

                            <div class="pos-modern-adjustment-label">
                                <strong id="round_off">
                                    @lang('lang_v1.round_off'):
                                </strong>
                            </div>

                            <span id="round_off_text">0</span>

                            <input type="hidden"
                                   name="round_off_amount"
                                   id="round_off_amount"
                                   value="0">
                        </div>
                    @endif

                </div>
            </details>
        </div>
    </div>
</div>
