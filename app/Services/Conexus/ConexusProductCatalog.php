<?php

namespace App\Services\Conexus;

use Illuminate\Support\Facades\DB;

class ConexusProductCatalog
{
    /**
     * Devuelve una fila por cada variación comercial.
     */
    public function obtenerPresentaciones(): array
    {
        $businessId = (int) config('conexus.business_id', 1);

        $marca = trim(
            (string) config('conexus.brand_name', 'UNILEVER')
        );

        $registros = DB::table('products as p')
            ->join('brands as b', function ($join) use ($businessId) {
                $join->on('b.id', '=', 'p.brand_id')
                    ->where('b.business_id', '=', $businessId);
            })
            ->join('product_variations as pv', function ($join) {
                $join->on('pv.product_id', '=', 'p.id');
            })
            ->join('variations as v', function ($join) {
                $join->on('v.product_variation_id', '=', 'pv.id');
            })
            ->leftJoin('units as u', function ($join) use ($businessId) {
                $join->on('u.id', '=', 'p.unit_id')
                    ->where('u.business_id', '=', $businessId);
            })
            ->where('p.business_id', $businessId)
            ->whereIn('p.type', ['single', 'variable'])
            ->whereRaw('UPPER(TRIM(b.name)) = UPPER(?)', [
                $marca,
            ])
            ->select(
                'p.id as producto_id',
                'p.name as producto',
                'p.sku as sku_producto',
                'p.type as tipo_producto',
                'p.unit_id',
                'p.tax',
                'p.tax_type',
                'p.is_inactive',
                'b.id as marca_id',
                'b.name as marca',
                'pv.id as grupo_variacion_id',
                'pv.name as atributo',
                'v.id as variacion_id',
                'v.name as valor_variacion',
                'v.sub_sku as sku_variacion',
                'u.actual_name as unidad_principal',
                'u.short_name as codigo_unidad',
                'v.default_purchase_price',
                'v.dpp_inc_tax',
                'v.default_sell_price',
                'v.sell_price_inc_tax',
            )
            ->orderBy('p.id')
            ->orderBy('pv.id')
            ->orderBy('v.id')
            ->get();

        return $registros->map(function ($item) {

            $esSimple = $item->tipo_producto === 'single';

            $codigo = trim(
                (string) ($item->sku_variacion ?: $item->sku_producto)
            );

            $nombre = trim((string) $item->producto);

            if (!$esSimple) {
                $nombre .= ' - ' . trim((string) $item->atributo);

                $nombre .= ' - ' . trim(
                    (string) $item->valor_variacion
                );
            }

            return [
                'producto_id' => $item->producto_id,
                'variacion_id' => $item->variacion_id,

                'codigo_producto' => $codigo,
                'nombre_producto' => $nombre,

                'tipo_producto' => $item->tipo_producto,

                'presentacion' => $esSimple
                    ? null
                    : $item->atributo,

                'contenido' => $esSimple
                    ? null
                    : $item->valor_variacion,

                'unidad_erp' => $item->unidad_principal,
                'codigo_unidad_erp' => $item->codigo_unidad,

                'marca' => $item->marca,

                'estado' => (int) $item->is_inactive === 1
                    ? 'I'
                    : 'A',

                'impuesto_erp' => $item->tax,
                'tipo_impuesto_erp' => $item->tax_type,
                'precio_compra_sin_igv' => $item->default_purchase_price,
                'precio_compra_con_igv' => $item->dpp_inc_tax,
                'precio_venta_sin_igv' => $item->default_sell_price,
                'precio_venta_con_igv' => $item->sell_price_inc_tax,
            ];

        })->all();
    }
}