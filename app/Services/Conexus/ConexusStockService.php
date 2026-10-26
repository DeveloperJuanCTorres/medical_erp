<?php

namespace App\Services\Conexus;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConexusStockService
{
    public function obtenerStockActual(): array
    {
        $businessId = (int) config('conexus.business_id', 1);
        $marca = config('conexus.brand_name', 'UNILEVER');

        $registros = DB::table('business_locations as bl')
            ->crossJoin('products as p')

            ->join('brands as b', 'b.id', '=', 'p.brand_id')

            ->join('product_variations as pv', function ($join) {
                $join->on('pv.product_id', '=', 'p.id');
            })

            ->join('variations as v', function ($join) {
                $join->on('v.product_variation_id', '=', 'pv.id')
                     ->on('v.product_id', '=', 'p.id');
            })

            ->leftJoin('variation_location_details as vld', function ($join) {
                $join->on('vld.location_id', '=', 'bl.id')
                     ->on('vld.variation_id', '=', 'v.id');
            })

            ->where('bl.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->where('b.name', $marca)
            ->where('bl.is_active', 1)
            ->whereNull('bl.deleted_at')

            ->select(
                'bl.id as almacen_id',
                'bl.location_id as codigo_almacen',
                'bl.name as nombre_almacen',
                'v.id as variacion_id',
                'v.sub_sku as codigo_producto'
            )

            ->selectRaw(
                'COALESCE(SUM(vld.qty_available), 0) as cantidad_stock'
            )

            ->groupBy(
                'bl.id',
                'bl.location_id',
                'bl.name',
                'v.id',
                'v.sub_sku'
            )

            ->orderBy('bl.id')
            ->orderBy('v.id')
            ->get();

        $resultado = [];

        foreach ($registros as $registro) {

            $codigoAlmacen = trim((string) $registro->codigo_almacen);
            $codigoProducto = trim((string) $registro->codigo_producto);
            $cantidad = (float) $registro->cantidad_stock;

            if ($codigoAlmacen === '' || strlen($codigoAlmacen) > 10) {
                throw new RuntimeException(
                    'Código de almacén inválido: ' . $codigoAlmacen
                );
            }

            if ($codigoProducto === '' || strlen($codigoProducto) > 25) {
                throw new RuntimeException(
                    'Código de producto inválido: ' . $codigoProducto
                );
            }

            if (!is_finite($cantidad) || floor($cantidad) != $cantidad) {
                throw new RuntimeException(
                    'Stock fraccionario o inválido para SKU ' .
                    $codigoProducto .
                    ' en almacén ' . $codigoAlmacen
                );
            }

            $resultado[] = [
                'almacen_id' => (int) $registro->almacen_id,
                'codigo_almacen' => $codigoAlmacen,
                'nombre_almacen' => $registro->nombre_almacen,
                'variacion_id' => (int) $registro->variacion_id,
                'codigo_producto' => $codigoProducto,
                'cantidad_stock' => (int) $cantidad,
                'unidad_inventario' => 'UNIDAD',
            ];
        }

        return $resultado;
    }
}
