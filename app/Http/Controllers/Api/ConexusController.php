<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Conexus\ConexusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Throwable;
use App\Services\Conexus\ConexusDateRange;
use App\Services\Conexus\ConexusStockService;
use App\Services\Conexus\ConexusStockMovementService;
use App\Services\Conexus\StockExporter;
use App\Services\Conexus\SalesExporter;
use Illuminate\Support\Facades\DB;
use App\Services\Conexus\ConexusCommercialService;

class ConexusController extends Controller
{
    protected ConexusService $conexus;

    public function __construct(ConexusService $conexus)
    {
        $this->conexus = $conexus;
    }

    /**
     * Valida el acceso a la integración.
     */
    private function autorizado(Request $request): bool
    {
        if (!config('conexus.enabled', false)) {
            return false;
        }

        $tokenConfigurado = (string) config(
            'conexus.api_token',
            ''
        );

        $tokenRecibido = (string) $request->bearerToken();

        if (
            strlen($tokenConfigurado) < 32 ||
            $tokenRecibido === ''
        ) {
            return false;
        }

        return hash_equals(
            $tokenConfigurado,
            $tokenRecibido
        );
    }

    /**
     * GET /api/conexus/estado
     */
    public function estado(Request $request)
    {
        if (!$this->autorizado($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        try {
            return response()->json([
                'success' => true,
                'data' => $this->conexus->obtenerEstado(),
            ]);

        } catch (Throwable $e) {

            Log::error('CONEXUS - Error de estado', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo consultar el estado.',
            ], 500);
        }
    }

    /**
     * POST /api/conexus/resumen
     */
    public function resumen(Request $request)
    {
        if (!$this->autorizado($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'fecha_inicio' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'fecha_fin' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:fecha_inicio',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Fechas inválidas.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $hoy = Carbon::now(
            config('conexus.timezone', 'America/Lima')
        );

        $fechaInicio = $request->input('fecha_inicio')
            ?: $hoy->copy()
                ->subMonthNoOverflow()
                ->startOfMonth()
                ->format('Y-m-d');

        $fechaFin = $request->input('fecha_fin')
            ?: $hoy->copy()
                ->endOfMonth()
                ->format('Y-m-d');

        if ($fechaInicio > $fechaFin) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha inicial no puede superar la final.',
            ], 422);
        }

        try {

            $resultado = $this->conexus->obtenerResumen(
                $fechaInicio,
                $fechaFin
            );

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ]);

        } catch (Throwable $e) {

            Log::error('CONEXUS - Error de resumen', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar el resumen.',
            ], 500);
        }
    }

    /**
     * GET /api/conexus/productos
     */
    public function productos(Request $request)
    {
        if (!$this->autorizado($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        try {
            $productos = $this->conexus->obtenerProductosConexus();

            return response()->json([
                'success' => true,
                'marca' => config('conexus.brand_name'),
                'total' => count($productos),
                'productos' => $productos,
            ]);

        } catch (Throwable $e) {
            Log::error('CONEXUS - Error al consultar productos', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar productos CONEXUS.',
            ], 500);
        }
    }

    /**
     * GET /api/conexus/presentaciones
     */
    public function presentaciones(
        Request $request,
        \App\Services\Conexus\ConexusProductCatalog $catalogo
    ) {
        if (!$this->autorizado($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        try {
            $productos = $catalogo->obtenerPresentaciones();

            return response()->json([
                'success' => true,
                'marca' => config('conexus.brand_name', 'UNILEVER'),
                'total_presentaciones' => count($productos),
                'presentaciones' => $productos,
            ]);

        } catch (\Throwable $e) {

            \Illuminate\Support\Facades\Log::error(
                'CONEXUS - Error de presentaciones',
                [
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron consultar las presentaciones.',
            ], 500);
        }
    }

    /**
     * POST /api/conexus/exportar-productos
     */
    public function exportarProductos(
        Request $request,
        \App\Services\Conexus\ProductExporter $exportador
    ) {
        if (!$this->autorizado($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        try {
            $resultado = $exportador->exportar();

            return response()->json([
                'success' => true,
                'message' => 'Productos.csv generado para validación.',
                'resultado' => $resultado,
            ]);

        } catch (\Throwable $e) {

            \Illuminate\Support\Facades\Log::error(
                'CONEXUS - Error exportando productos',
                ['error' => $e->getMessage()]
            );

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    
    public function periodo(
        Request $request,
        ConexusDateRange $fechas
    ) {
        if (!$this->autorizado($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.'
            ], 401);
        }

        try {
            $periodo = $fechas->resolver(
                $request->input('fecha_inicio'),
                $request->input('fecha_fin')
            );

            return response()->json([
                'success' => true,
                'message' => 'Periodo CONEXUS calculado correctamente.',
                'periodo' => $periodo
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    
    public function stockActual(
        Request $request,
        ConexusStockService $stockService
    ) {
        if (!$this->autorizado($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.'
            ], 401);
        }

        try {
            $registros = $stockService->obtenerStockActual();

            return response()->json([
                'success' => true,
                'message' => 'Stock actual consultado correctamente.',
                'total_registros' => count($registros),
                'stock_total' => array_sum(
                    array_column($registros, 'cantidad_stock')
                ),
                'tipo_consulta' => 'stock_actual_no_historico',
                'registros' => $registros
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    
    public function movimientosStock(
        Request $request,
        ConexusDateRange $fechas,
        ConexusStockMovementService $movimientosService
    ) {
        if (!$this->autorizado($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.'
            ], 401);
        }

        try {
            $periodo = $fechas->resolver(
                $request->input('fecha_inicio'),
                $request->input('fecha_fin')
            );

            $movimientos = $movimientosService->obtenerMovimientos(
                $periodo['desde'],
                $periodo['hasta_exclusivo']
            );

            return response()->json([
                'success' => true,
                'periodo' => $periodo,
                'total_registros' => count($movimientos),
                'movimientos' => $movimientos
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar movimientos de stock.'
            ], 500);
        }
    }

    
    public function exportarStock(
        Request $request,
        ConexusDateRange $fechas,
        StockExporter $exportador
    ) {
        if (!$this->autorizado($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.'
            ], 401);
        }

        try {
            $periodo = $fechas->resolver(
                $request->input('fecha_inicio'),
                $request->input('fecha_fin')
            );

            $resultado = $exportador->exportarPrueba($periodo);

            return response()->json([
                'success' => true,
                'message' => 'Stock.csv de prueba generado correctamente.',
                'resultado' => $resultado
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
   
    
    public function exportarVentas(
        Request $request,
        SalesExporter $exporter,
        ConexusCommercialService $commercialService
    ) {
        $datos = $request->validate([
            'fecha_inicio' => 'required|date_format:Y-m-d',
            'fecha_fin' => 'required|date_format:Y-m-d|after_or_equal:fecha_inicio',
        ]);

        try {
            $resultado = $exporter->exportar(
                $datos['fecha_inicio'],
                $datos['fecha_fin'],
                function (array $registro) use ($commercialService) {
                    return $commercialService->resolver($registro);
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'Ventas.csv generado correctamente.',
                'resultado' => $resultado,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar Ventas.csv.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }



}