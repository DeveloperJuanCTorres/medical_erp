
@forelse($products as $product)

    @php
        // Imagen predeterminada para cualquier tipo de negocio.
        $defaultImage = asset('img/product-placeholder.png');

        // Prioridad: multimedia, imagen del producto, predeterminada.
        $productImage = $defaultImage;

        if ($product->media->isNotEmpty()) {
            $mediaUrl = $product->media->first()->display_url;

            if (!empty($mediaUrl)) {
                $productImage = $mediaUrl;
            }
        } elseif (!empty($product->product_image)) {
            $productImage = asset(
                'uploads/img/' . rawurlencode($product->product_image)
            );
        }
    @endphp

    <div class="col-md-3 col-xs-4 product_list no-print">

        <div class="product_box pos-modern-product-card"
             data-variation_id="{{ $product->id }}"
             title="{{ $product->name }} @if($product->type == 'variable')- {{ $product->variation }} @endif ({{ $product->sub_sku }}) @if(!empty($show_prices)) @lang('lang_v1.default') - @format_currency($product->selling_price) @foreach($product->group_prices as $group_price) @if(array_key_exists($group_price->price_group_id, $allowed_group_prices)) {{ $allowed_group_prices[$group_price->price_group_id] }} - @format_currency($group_price->price_inc_tax) @endif @endforeach @endif">

            <div class="image-container pos-modern-product-image">
                <img
                    src="{{ $productImage }}"
                    alt="{{ $product->name }}"
                    loading="lazy"
                    draggable="false"
                    onerror="this.onerror=null;this.src='{{ $defaultImage }}';"
                >
            </div>

            <div class="text_div pos-modern-product-info">

                <span class="pos-modern-product-name">
                    {{ $product->name }}

                    @if($product->type == 'variable')
                        - {{ $product->variation }}
                    @endif
                </span>

                <small class="pos-modern-product-sku">
                    {{ $product->sub_sku }}
                </small>

                @if(!empty($show_prices))
                    <div class="pos-modern-product-price">
                        @format_currency($product->selling_price)
                    </div>
                @endif

            </div>

        </div>
    </div>

@empty

    <input type="hidden" id="no_products_found">

    <div class="col-md-12">
        <div class="pos-modern-empty-products">
            <i class="fas fa-box-open"></i>
            <h4>@lang('lang_v1.no_products_to_display')</h4>
        </div>
    </div>

@endforelse
