<?php

namespace App\Services\Conexus;

use Illuminate\Support\Facades\DB;

class ConexusStockMovementService
{
    public function obtenerMovimientos(
        string $desde,
        string $hastaExclusivo
    ): array {
        $businessId = (int) config('conexus.business_id', 1);
        $marca = config('conexus.brand_name', 'UNILEVER');

        // Movimientos de entrada: stock inicial.
        $entradas = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->join('variations as v', 'v.id', '=', 'pl.variation_id')
            ->where('t.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->where('b.name', $marca)
            ->where('t.type', 'opening_stock')
            ->where('t.status', 'received')
            ->where('t.transaction_date', '>=', $desde)
            ->where('t.transaction_date', '<', $hastaExclusivo)
            ->select(
                't.location_id as almacen_id',
                'v.sub_sku as codigo_producto',
                't.transaction_date as fecha'
            )
            ->selectRaw('SUM(pl.quantity) as ingresos')
            ->selectRaw('0 as salidas')
            ->groupBy(
                't.location_id',
                'v.sub_sku',
                't.transaction_date'
            );

        // Movimientos de salida: ventas finalizadas.
        $salidas = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->join('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->where('t.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->where('b.name', $marca)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.transaction_date', '>=', $desde)
            ->where('t.transaction_date', '<', $hastaExclusivo)
            ->select(
                't.location_id as almacen_id',
                'v.sub_sku as codigo_producto',
                't.transaction_date as fecha'
            )
            ->selectRaw('0 as ingresos')
            ->selectRaw('SUM(tsl.quantity) as salidas')
            ->groupBy(
                't.location_id',
                'v.sub_sku',
                't.transaction_date'
            );

        
        // SALIDAS POR TRANSFERENCIAS
        $salidasTransferencia = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->join('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->where('t.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->where('b.name', $marca)
            ->where('t.type', 'sell_transfer')
            ->where('t.status', 'final')
            ->where('t.transaction_date', '>=', $desde)
            ->where('t.transaction_date', '<', $hastaExclusivo)
            ->select(
                't.location_id as almacen_id',
                'v.sub_sku as codigo_producto',
                't.transaction_date as fecha'
            )
            ->selectRaw('0 as ingresos')
            ->selectRaw('SUM(tsl.quantity) as salidas')
            ->groupBy(
                't.location_id',
                'v.sub_sku',
                't.transaction_date'
            );

        // INGRESOS POR TRANSFERENCIAS RECIBIDAS
        $entradasTransferencia = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->join('variations as v', 'v.id', '=', 'pl.variation_id')
            ->where('t.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->where('b.name', $marca)
            ->where('t.type', 'purchase_transfer')
            ->where('t.status', 'received')
            ->whereNotNull('t.transfer_parent_id')
            ->where('t.transaction_date', '>=', $desde)
            ->where('t.transaction_date', '<', $hastaExclusivo)
            ->select(
                't.location_id as almacen_id',
                'v.sub_sku as codigo_producto',
                't.transaction_date as fecha'
            )
            ->selectRaw('SUM(pl.quantity) as ingresos')
            ->selectRaw('0 as salidas')
            ->groupBy(
                't.location_id',
                'v.sub_sku',
                't.transaction_date'
            );

        
        // INGRESOS POR COMPRAS RECIBIDAS
        $comprasRecibidas = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->join('variations as v', 'v.id', '=', 'pl.variation_id')
            ->where('t.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->where('b.name', $marca)
            ->where('t.type', 'purchase')
            ->where('t.status', 'received')
            ->where('t.transaction_date', '>=', $desde)
            ->where('t.transaction_date', '<', $hastaExclusivo)
            ->select(
                't.location_id as almacen_id',
                'v.sub_sku as codigo_producto',
                't.transaction_date as fecha'
            )
            ->selectRaw('SUM(pl.quantity) as ingresos')
            ->selectRaw('0 as salidas')
            ->groupBy(
                't.location_id',
                'v.sub_sku',
                't.transaction_date'
            );

         $devolucionesCompra = DB::table('transactions as r')
            ->join('transactions as t', 't.id', '=', 'r.return_parent_id')
            ->join('purchase_lines as pl', 'pl.transaction_id', '=', 't.id')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->join('variations as v', 'v.id', '=', 'pl.variation_id')
            ->where('r.business_id', $businessId)
            ->where('t.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->where('b.name', $marca)
            ->where('r.type', 'purchase_return')
            ->where('r.status', 'final')
            ->where('r.transaction_date', '>=', $desde)
            ->where('r.transaction_date', '<', $hastaExclusivo)
            ->where('pl.quantity_returned', '>', 0)
            ->select(
                'r.location_id as almacen_id',
                'v.sub_sku as codigo_producto',
                'r.transaction_date as fecha'
            )
            ->selectRaw('0 as ingresos')
            ->selectRaw('SUM(pl.quantity_returned) as salidas')
            ->groupBy(
                'r.location_id',
                'v.sub_sku',
                'r.transaction_date'
            );

        
        $ajustesStock = DB::table('stock_adjustment_lines as sal')
            ->join('transactions as t', 't.id', '=', 'sal.transaction_id')
            ->join('products as p', 'p.id', '=', 'sal.product_id')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->join('variations as v', 'v.id', '=', 'sal.variation_id')
            ->where('t.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->where('b.name', $marca)
            ->where('t.type', 'stock_adjustment')
            ->where('t.transaction_date', '>=', $desde)
            ->where('t.transaction_date', '<', $hastaExclusivo)
            ->select(
                't.location_id as almacen_id',
                'v.sub_sku as codigo_producto',
                't.transaction_date as fecha'
            )
            ->selectRaw('0 as ingresos')
            ->selectRaw('SUM(sal.quantity) as salidas')
            ->groupBy(
                't.location_id',
                'v.sub_sku',
                't.transaction_date'
            );

        
        $devolucionesVenta = DB::table('transactions as r')
            ->join('transactions as t', 't.id', '=', 'r.return_parent_id')
            ->join('transaction_sell_lines as tsl', 'tsl.transaction_id', '=', 't.id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->join('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->where('r.business_id', $businessId)
            ->where('t.business_id', $businessId)
            ->where('p.business_id', $businessId)
            ->where('b.name', $marca)
            ->where('r.type', 'sell_return')
            ->where('r.status', 'final')
            ->where('r.transaction_date', '>=', $desde)
            ->where('r.transaction_date', '<', $hastaExclusivo)
            ->where('tsl.quantity_returned', '>', 0)
            ->select(
                'r.location_id as almacen_id',
                'v.sub_sku as codigo_producto',
                'r.transaction_date as fecha'
            )
            ->selectRaw('SUM(tsl.quantity_returned) as ingresos')
            ->selectRaw('0 as salidas')
            ->groupBy(
                'r.location_id',
                'v.sub_sku',
                'r.transaction_date'
            );



        $movimientos = DB::query()
            ->fromSub($entradas->unionAll($salidas)->unionAll($salidasTransferencia)->unionAll($entradasTransferencia)->unionAll($comprasRecibidas)->unionAll($devolucionesCompra)->unionAll($ajustesStock)->unionAll($devolucionesVenta), 'movimientos')
            ->select(
                'almacen_id',
                'codigo_producto'
            )
            ->selectRaw('DATE(fecha) as fecha_proceso')
            ->selectRaw('SUM(ingresos) as ingresos')
            ->selectRaw('SUM(salidas) as salidas')
            ->groupBy(
                'almacen_id',
                'codigo_producto',
                DB::raw('DATE(fecha)')
            )
            ->orderBy('fecha_proceso')
            ->orderBy('almacen_id')
            ->orderBy('codigo_producto')
            ->get();

        
       


        return $movimientos->map(function ($movimiento) {
            return [
                'almacen_id' => (int) $movimiento->almacen_id,
                'codigo_producto' => $movimiento->codigo_producto,
                'fecha_proceso' => $movimiento->fecha_proceso,
                'ingresos' => (float) $movimiento->ingresos,
                'salidas' => (float) $movimiento->salidas,
            ];
        })->all();
    }
}
