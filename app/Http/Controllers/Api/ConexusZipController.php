<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Conexus\ConexusZipService;
use App\Services\Conexus\ConexusDateRange;
use App\Services\Conexus\ProductExporter;
use App\Services\Conexus\StockExporter;
use App\Services\Conexus\SalesExporter;
use App\Services\Conexus\ConexusCommercialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class ConexusZipController extends Controller
{
    public function generar(
        Request $request,
        ConexusDateRange $fechas,
        ProductExporter $productos,
        StockExporter $stock,
        SalesExporter $ventas,
        ConexusCommercialService $comercial,
        ConexusZipService $zipService
    ) {
        $datos = $request->validate([
            'fecha_inicio' => 'nullable|date_format:Y-m-d',
            'fecha_fin' => 'nullable|date_format:Y-m-d',
        ]);

        $hoy = Carbon::now(
            config('conexus.timezone', 'America/Lima')
        );

        $fechaInicio = $datos['fecha_inicio'] ?? null;
        $fechaFin = $datos['fecha_fin'] ?? null;

        $fechaInicio = $fechaInicio ?: $hoy->copy()
            ->subMonthNoOverflow()
            ->startOfMonth()
            ->format('Y-m-d');

        $fechaFin = $fechaFin ?: $hoy->copy()
            ->endOfMonth()
            ->format('Y-m-d');

        if ($fechaInicio > $fechaFin) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha inicial no puede superar la fecha final.',
            ], 422);
        }

        try {
            // 1. Resolver el período.
            // Período solicitado para las ventas.

            $periodoVentas = $fechas->resolver(
                $fechaInicio,
                $fechaFin
            );

            // El stock simplificado se genera con fecha de corte actual.
            $fechaStock = $hoy->format('Y-m-d');

            $periodoStock = $fechas->resolver(
                $fechaStock,
                $fechaStock
            );

            $resultadoProductos = $productos->exportar();

            $resultadoStock = $stock->exportarPrueba($periodoStock);

            $resultadoVentas = $ventas->exportar(
                $fechaInicio,
                $fechaFin,
                function (array $registro) use ($comercial) {
                    return $comercial->resolver($registro);
                }
            );

            $resultadoZip = $zipService->generar();

            return response()->json([
                'success' => true,
                'message' => 'Output.zip generado correctamente.',
                'periodo' => [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'fecha_corte_stock' => $fechaStock,
                ],
                'archivos_generados' => [
                    'productos' => $resultadoProductos,
                    'stock' => $resultadoStock,
                    'ventas' => $resultadoVentas,
                ],
                'resultado' => $resultadoZip,
            ]);

        } catch (Throwable $e) {

            Log::error('CONEXUS - Error generando Output.zip', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar Output.zip.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function descargar(
        ConexusZipService $zipService
    ) {
        try {
            // Descargar el ZIP existente sin regenerar los CSV.
            $ruta = storage_path('app/conexus/zip/Output.zip');

            if (!is_file($ruta)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Output.zip no existe. Genéralo primero.',
                ], 404);
            }

            return response()->download(
                $ruta,
                'Output.zip',
                ['Content-Type' => 'application/zip']
            );

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
