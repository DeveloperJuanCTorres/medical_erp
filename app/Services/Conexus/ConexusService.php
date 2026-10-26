<?php

namespace App\Services\Conexus;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConexusService
{
    protected int $businessId;

    public function __construct()
    {
        $this->businessId = (int) config('conexus.business_id', 1);
    }

    /**
     * Comprueba la configuración de la integración.
     */
    public function obtenerEstado(): array
    {
        $empresa = DB::table('business')
            ->where('id', $this->businessId)
            ->first(['id', 'name']);

        if (!$empresa) {
            throw new \RuntimeException(
                'No existe la empresa configurada para CONEXUS.'
            );
        }

        return [
            'integracion' => 'CONEXUS 3.2',
            'empresa' => $empresa->name,
            'business_id' => $empresa->id,
            'estado' => 'conectado',
            'fecha_servidor' => Carbon::now(
                config('conexus.timezone', 'America/Lima')
            )->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtiene un resumen de la información disponible.
     * No modifica ningún registro del ERP.
     */
    public function obtenerResumen(
        string $fechaInicio,
        string $fechaFin
    ): array {

        $productos = DB::table('products')
            ->where('business_id', $this->businessId)
            ->count();

        $ventas = DB::table('transactions')
            ->where('business_id', $this->businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->whereBetween('transaction_date', [
                $fechaInicio . ' 00:00:00',
                $fechaFin . ' 23:59:59',
            ])
            ->count();

        $almacenes = DB::table('business_locations')
            ->where('business_id', $this->businessId)
            ->count();

        return [
            'business_id' => $this->businessId,

            'periodo' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ],

            'resumen' => [
                'productos' => $productos,
                'ventas' => $ventas,
                'almacenes' => $almacenes,
            ],

            'exportacion' => [
                'estado' => 'pendiente_implementacion',
                'archivos' => [
                    'Productos.csv',
                    'Ventas.csv',
                    'Stock.csv',
                ],
            ],
        ];
    }


    /**
     * Obtiene productos de la marca configurada para CONEXUS.
     */
    public function obtenerProductosConexus(): array
    {
        $productos = DB::table('products as p')
            ->join('brands as b', 'b.id', '=', 'p.brand_id')
            ->where('p.business_id', $this->businessId)
            ->where('b.business_id', $this->businessId)
            ->whereRaw('UPPER(TRIM(b.name)) = ?', [
                mb_strtoupper(trim(config('conexus.brand_name', 'UNILEVER')))
            ])
            ->select(
                'p.id',
                'p.name',
                'p.sku',
                'p.brand_id',
                'b.name as marca'
            )
            ->orderBy('p.id')
            ->get();

        return $productos->toArray();
    }
}