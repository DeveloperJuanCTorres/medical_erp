<?php

namespace App\Services\Conexus;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConexusSalesService
{
    private $businessId = 1;
    private $brandId = 22;

    /**
     * Obtiene ventas y devoluciones del periodo.
     *
     * La fecha final es inclusiva.
     */
    public function obtenerMovimientos($fechaInicio, $fechaFin)
    {
        $desde = Carbon::parse($fechaInicio)
            ->startOfDay()
            ->format('Y-m-d H:i:s');

        $hasta = Carbon::parse($fechaFin)
            ->addDay()
            ->startOfDay()
            ->format('Y-m-d H:i:s');

        $ventas = DB::table('transactions as t')
            ->join(
                'transaction_sell_lines as tsl',
                'tsl.transaction_id',
                '=',
                't.id'
            )
            ->join(
                'products as p',
                'p.id',
                '=',
                'tsl.product_id'
            )
            ->join(
                'variations as v',
                'v.id',
                '=',
                'tsl.variation_id'
            )
            ->leftJoin(
                'contacts as c',
                'c.id',
                '=',
                't.contact_id'
            )
            ->leftJoin(
                'units as u',
                'u.id',
                '=',
                'p.unit_id'
            )
            ->leftJoin(
                'units as su',
                'su.id',
                '=',
                'tsl.sub_unit_id'
            )
            ->where('t.business_id', $this->businessId)
            ->where('p.business_id', $this->businessId)
            ->where('p.brand_id', $this->brandId)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.transaction_date', '>=', $desde)
            ->where('t.transaction_date', '<', $hasta)
            ->select(
                't.id as transaccion_id',
                't.invoice_no as documento',
                't.transaction_date as fecha',
                't.contact_id as cliente_id',
                't.created_by as usuario_creador',
                't.commission_agent as vendedor_comisionista',
                't.payment_status',
                't.pay_term_type',
                't.discount_type as descuento_documento_tipo',
                't.discount_amount as descuento_documento',
                't.final_total as total_documento',

                'c.contact_id as codigo_cliente',
                'c.customer_group_id',

                'tsl.id as linea_id',
                'tsl.product_id',
                'tsl.variation_id',
                'tsl.quantity',
                'tsl.quantity_returned',
                'tsl.unit_price_before_discount',
                'tsl.unit_price',
                'tsl.unit_price_inc_tax',
                'tsl.item_tax',
                'tsl.line_discount_type',
                'tsl.line_discount_amount',
                'tsl.sub_unit_id',

                'v.sub_sku as codigo_producto',

                'p.unit_id',
                'u.actual_name as unidad_principal',
                'su.actual_name as sub_unidad'
            )
            ->orderBy('t.transaction_date')
            ->orderBy('t.id')
            ->orderBy('tsl.id')
            ->get();

        $resultadoVentas = $ventas->map(function ($venta) {
            $venta->tipo_conexus = $this->obtenerTipoDocumento(
                'sell',
                $venta->documento
            );

            $venta->cantidad_venta = (float) $venta->quantity;

            $venta->precio_unitario = (float) $venta->unit_price;

            $venta->importe_sin_impuesto = round(
                (float) $venta->quantity *
                (float) $venta->unit_price,
                4
            );

            $venta->importe_con_impuesto = round(
                (float) $venta->quantity *
                (float) $venta->unit_price_inc_tax,
                4
            );

            $venta->unidad_venta = $venta->sub_unidad
                ?: $venta->unidad_principal;

            return $venta;
        });

        /*
         * Las devoluciones se recuperan separadamente.
         *
         * No calculamos todavía sus líneas porque debemos
         * comprobar cómo relaciona el ERP los productos
         * devueltos con la transacción original.
         */
        $devoluciones = DB::table('transactions as r')
            ->join(
                'transactions as original',
                'original.id',
                '=',
                'r.return_parent_id'
            )
            ->where('r.business_id', $this->businessId)
            ->where('original.business_id', $this->businessId)
            ->where('r.type', 'sell_return')
            ->where('r.status', 'final')
            ->where('original.type', 'sell')
            ->where('r.transaction_date', '>=', $desde)
            ->where('r.transaction_date', '<', $hasta)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('transaction_sell_lines as tsl')
                    ->join(
                        'products as p',
                        'p.id',
                        '=',
                        'tsl.product_id'
                    )
                    ->whereColumn(
                        'tsl.transaction_id',
                        'original.id'
                    )
                    ->where(
                        'p.brand_id',
                        $this->brandId
                    )
                    ->where(
                        'p.business_id',
                        $this->businessId
                    );
            })
            ->select(
                'r.id as devolucion_id',
                'r.invoice_no as documento_devolucion',
                'r.transaction_date as fecha_devolucion',
                'r.final_total as importe_devolucion',
                'r.return_parent_id',
                'original.invoice_no as documento_original',
                'original.transaction_date as fecha_original',
                'original.contact_id as cliente_id'
            )
            ->orderBy('r.transaction_date')
            ->orderBy('r.id')
            ->get();

        return [
            'ventas' => $resultadoVentas,
            'devoluciones_pendientes' => $devoluciones,
            'total_lineas_venta' => $resultadoVentas->count(),
            'total_devoluciones_pendientes' => $devoluciones->count(),
        ];
    }

    /**
     * Clasificación comercial para CONEXUS.
     */
    
    private function obtenerTipoDocumento(
        string $tipoTransaccion,
        string $numeroDocumento
    ): ?string {
        $tipoTransaccion = strtolower(trim($tipoTransaccion));
        $numeroDocumento = strtoupper(trim($numeroDocumento));

        if ($tipoTransaccion === 'sell_return') {
            return 'NC';
        }

        if ($tipoTransaccion !== 'sell') {
            return null;
        }

        // Facturas: F001-0001, FFF1-0099, FFF2-0001
        if (preg_match('/^F[A-Z0-9]*\d-\d+$/', $numeroDocumento)) {
            return 'FA';
        }

        // Boletas: B001-0001, BBB1-0001
        if (preg_match('/^B[A-Z0-9]*\d-\d+$/', $numeroDocumento)) {
            return 'BO';
        }

        return null;
    }


    
    public function obtenerNotasCredito($fechaInicio, $fechaFin)
    {
        $desde = \Carbon\Carbon::parse($fechaInicio)
            ->startOfDay();

        $hasta = \Carbon\Carbon::parse($fechaFin)
            ->addDay()
            ->startOfDay();

        return DB::table('transactions as r')
            ->join(
                'transactions as original',
                'original.id',
                '=',
                'r.return_parent_id'
            )
            ->join(
                'transaction_sell_lines as tsl',
                'tsl.transaction_id',
                '=',
                'original.id'
            )
            ->join(
                'products as p',
                'p.id',
                '=',
                'tsl.product_id'
            )
            ->join(
                'variations as v',
                'v.id',
                '=',
                'tsl.variation_id'
            )
            ->leftJoin(
                'contacts as c',
                'c.id',
                '=',
                'original.contact_id'
            )
            ->leftJoin(
                'units as u',
                'u.id',
                '=',
                'p.unit_id'
            )
            ->leftJoin(
                'units as su',
                'su.id',
                '=',
                'tsl.sub_unit_id'
            )
            ->where('r.business_id', $this->businessId)
            ->where('original.business_id', $this->businessId)
            ->where('p.business_id', $this->businessId)
            ->where('p.brand_id', $this->brandId)
            ->where('r.type', 'sell_return')
            ->where('r.status', 'final')
            ->where('original.type', 'sell')
            ->where('r.transaction_date', '>=', $desde)
            ->where('r.transaction_date', '<', $hasta)
            ->where('tsl.quantity_returned', '>', 0)
            ->select(
                'r.id as devolucion_id',
                'r.invoice_no as documento',
                'r.transaction_date as fecha',
                'r.final_total as total_devolucion',

                'original.id as venta_original_id',
                'original.invoice_no as documento_original',
                'original.transaction_date as fecha_original',
                'original.contact_id as cliente_id',

                'c.contact_id as codigo_cliente',

                'tsl.id as linea_original_id',
                'tsl.product_id',
                'tsl.variation_id',
                'tsl.quantity_returned',
                'tsl.unit_price',
                'tsl.unit_price_inc_tax',
                'tsl.sub_unit_id',

                'v.sub_sku as codigo_producto',
                'u.actual_name as unidad_principal',
                'su.actual_name as sub_unidad'
            )
            ->orderBy('r.transaction_date')
            ->orderBy('r.id')
            ->orderBy('tsl.id')
            ->get()
            ->map(function ($linea) {

                $cantidad = (float) $linea->quantity_returned;

                $linea->tipo_conexus = 'NC';

                $linea->cantidad_venta = -$cantidad;

                $linea->precio_unitario =
                    (float) $linea->unit_price;

                $linea->importe_sin_impuesto = round(
                    -$cantidad * (float) $linea->unit_price,
                    4
                );

                $linea->importe_con_impuesto = round(
                    -$cantidad * (float) $linea->unit_price_inc_tax,
                    4
                );

                $linea->unidad_venta = $linea->sub_unidad
                    ?: $linea->unidad_principal;

                return $linea;
            });
    }

}
