<?php

namespace App\Services\Conexus;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConexusCommercialService
{
    public function resolver(array $registro): array
    {
        $clienteId = (int) $registro['cliente_id'];

        $cliente = DB::table('contacts')
            ->where('id', $clienteId)
            ->where('business_id', 1)
            ->first();

        if (!$cliente) {
            throw new RuntimeException(
                "Cliente no encontrado: {$clienteId}"
            );
        }

        $clienteConfig = config(
            "conexus_comercial.clientes.equivalencias.{$clienteId}",
            config('conexus_comercial.clientes.predeterminado', [])
        );

        $vendedor = config(
            'conexus_comercial.vendedores.predeterminado',
            []
        );

        
        $venta = DB::table('transactions')
            ->where('business_id', 1)
            ->where('invoice_no', $registro['documento'])
            ->where('type', 'sell')
            ->first();

        if (!$venta) {
            throw new RuntimeException(
                'Venta no encontrada: ' . $registro['documento']
            );
        }

        $condicionCompra = config(
            'conexus_comercial.condiciones_compra.por_estado_pago.'
            . $venta->payment_status
        );

        if (!$condicionCompra) {
            throw new RuntimeException(
                'Estado de pago sin equivalencia CONEXUS: '
                . $venta->payment_status
            );
        }


        return [
            'codigo_distribuidor' =>
                config('conexus.distributor_code'),

            'origen' =>
                config('conexus.ventas.origen', 'OFICINA'),

            'codigo_cliente' =>
                $cliente->contact_id,

            'canal_cliente' =>
                $clienteConfig['canal'] ?? null,

            'tipo_negocio' =>
                $clienteConfig['tipo_negocio'] ?? null,

            'codigo_vendedor' =>
                $vendedor['codigo'] ?? null,

            'canal_vendedor' =>
                $vendedor['canal'] ?? null,

            'ruta' => 'N/D',

            'condicion_compra' => $condicionCompra,

            'factor_conversion' => 1,
        ];
    }
}
