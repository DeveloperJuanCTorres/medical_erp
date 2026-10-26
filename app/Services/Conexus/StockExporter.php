<?php

namespace App\Services\Conexus;

use Carbon\CarbonImmutable;
use RuntimeException;

class StockExporter
{
    public function __construct(
        private ConexusStockService $stockService,
        private ConexusStockMovementService $movementService
    ) {
    }

    public function exportarPrueba(array $periodo): array
    {
        if (!config('conexus.test_mode', false)) {
            throw new RuntimeException(
                'Esta exportación requiere CONEXUS_TEST_MODE=true.'
            );
        }

        $timezone = config(
            'conexus.timezone',
            'America/Lima'
        );

        $inicio = CarbonImmutable::parse(
            $periodo['fecha_inicio'],
            $timezone
        )->startOfDay();

        $fin = CarbonImmutable::parse(
            $periodo['fecha_fin'],
            $timezone
        )->startOfDay();

        $ahora = CarbonImmutable::now($timezone);
        $hoy = $ahora->startOfDay();

        if ($inicio->greaterThan($fin)) {
            throw new RuntimeException(
                'La fecha inicial no puede ser mayor que la final.'
            );
        }

        /*
         * Esta versión genera un corte ACTUAL.
         *
         * No reconstruye saldos históricos.
         * No presenta el stock de hoy como si
         * perteneciera a una fecha anterior.
         *
         * fecha_fin debe ser hoy.
         */
        if (!$fin->equalTo($hoy)) {
            throw new RuntimeException(
                'Esta exportación simplificada solo admite ' .
                'la fecha actual como fecha_fin. ' .
                'No hay un snapshot histórico disponible ' .
                'para la fecha solicitada.'
            );
        }

        $codigoDistribuidor = trim(
            (string) config('conexus.distributor_code', '')
        );

        if ($codigoDistribuidor === '') {
            throw new RuntimeException(
                'Falta configurar el código del distribuidor.'
            );
        }

        /*
         * 1. STOCK ACTUAL DEL ERP
         */
        $stockActual = $this->stockService
            ->obtenerStockActual();

        /*
         * 2. MOVIMIENTOS DEL DÍA
         *
         * Usamos la consulta existente.
         * No modificamos el ERP.
         */
        $movimientos = $this->movementService
            ->obtenerMovimientos(
                $hoy->format('Y-m-d 00:00:00'),
                $hoy->addDay()->format('Y-m-d 00:00:00')
            );

        /*
         * 3. AGRUPAR INGRESOS Y SALIDAS
         */
        $movimientosPorClave = [];

        foreach ($movimientos as $movimiento) {
            $clave = $movimiento['almacen_id']
                . '|'
                . $movimiento['codigo_producto'];

            if (!isset($movimientosPorClave[$clave])) {
                $movimientosPorClave[$clave] = [
                    'ingresos' => 0.0,
                    'salidas' => 0.0,
                ];
            }

            $movimientosPorClave[$clave]['ingresos'] +=
                (float) $movimiento['ingresos'];

            $movimientosPorClave[$clave]['salidas'] +=
                (float) $movimiento['salidas'];
        }

        /*
         * 4. GENERAR LAS 35 COLUMNAS
         */
        $filas = [];
        $clavesExportadas = [];

        $totalStock = 0;
        $totalIngresos = 0;
        $totalSalidas = 0;

        $fechaProceso = $ahora->format('Y-m-d H:i:s');
        $periodoConexus = $hoy->format('Ym');

        foreach ($stockActual as $producto) {
            $clave = $producto['almacen_id']
                . '|'
                . $producto['codigo_producto'];

            if (isset($clavesExportadas[$clave])) {
                throw new RuntimeException(
                    'Producto y almacén duplicados: ' . $clave
                );
            }

            $clavesExportadas[$clave] = true;

            $saldo = (float) $producto['cantidad_stock'];

            $ingresos = $movimientosPorClave[$clave]
                ['ingresos'] ?? 0;

            $salidas = $movimientosPorClave[$clave]
                ['salidas'] ?? 0;

            /*
             * Validación de cantidades.
             * CONEXUS requiere stock en
             * unidades de inventario.
             */
            foreach (
                [
                    'stock' => $saldo,
                    'ingresos' => $ingresos,
                    'salidas' => $salidas,
                ] as $nombre => $cantidad
            ) {
                if (
                    !is_finite($cantidad) ||
                    $cantidad < 0 ||
                    abs($cantidad - round($cantidad)) > 0.00001
                ) {
                    throw new RuntimeException(
                        'Cantidad inválida en ' .
                        $nombre . ' para ' . $clave
                    );
                }
            }

            $saldo = (int) round($saldo);
            $ingresos = (int) round($ingresos);
            $salidas = (int) round($salidas);

            $unidadInventario = trim(
                (string) ($producto['unidad_inventario'] ?? '')
            );

            if ($unidadInventario === '') {
                throw new RuntimeException(
                    'Falta unidad de inventario para ' . $clave
                );
            }

            $campos = [
                // 1-10: CAMPOS BÁSICOS
                $codigoDistribuidor,
                (string) $producto['codigo_almacen'],
                (string) $producto['nombre_almacen'],
                (string) $producto['codigo_producto'],
                (string) $saldo,
                $unidadInventario,
                $fechaProceso,
                (string) $ingresos,
                (string) $salidas,
                $periodoConexus,

                // 11-20: CAMPOS NO BÁSICOS
                '0',            // ValorIngresos
                '0',            // ValorSalidas
                'DISPONIBLE',   // TipoInventario
                'N/D',          // TipoVencimiento
                'N/D',          // CodigoBonificacionStock
                'N/D',          // BonificacionStock
                '0',            // PesoStock
                'N/D',          // Lote
                '1900-01-01',   // FechaVencimiento
                '0',            // ValorStock
            ];

            // 21-35: REF1 - REF15
            $campos = array_merge(
                $campos,
                array_fill(0, 15, 'N/D')
            );

            if (count($campos) !== 35) {
                throw new RuntimeException(
                    'Stock.csv debe contener 35 columnas.'
                );
            }

            foreach ($campos as $campo) {
                $texto = (string) $campo;

                if (
                    str_contains($texto, '|') ||
                    str_contains($texto, "\r") ||
                    str_contains($texto, "\n")
                ) {
                    throw new RuntimeException(
                        'Carácter inválido en Stock.csv.'
                    );
                }
            }

            $filas[] = implode('|', $campos);

            $totalStock += $saldo;
            $totalIngresos += $ingresos;
            $totalSalidas += $salidas;
        }

        /*
         * 5. VERIFICAR MOVIMIENTOS SIN PRODUCTO
         */
        foreach ($movimientosPorClave as $clave => $movimiento) {
            if (!isset($clavesExportadas[$clave])) {
                throw new RuntimeException(
                    'Existe un movimiento sin registro ' .
                    'de stock para: ' . $clave
                );
            }
        }

        /*
         * 6. GUARDAR Stock.csv
         */
        $directorio = storage_path(
            'app/conexus/data/unificado'
        );

        if (
            !is_dir($directorio) &&
            !mkdir($directorio, 0775, true) &&
            !is_dir($directorio)
        ) {
            throw new RuntimeException(
                'No se pudo crear el directorio CONEXUS.'
            );
        }

        $ruta = $directorio
            . DIRECTORY_SEPARATOR
            . 'Stock.csv';

        $temporal = tempnam($directorio, 'stock_');

        if ($temporal === false) {
            throw new RuntimeException(
                'No se pudo crear el archivo temporal.'
            );
        }

        try {
            $contenido = empty($filas)
                ? ''
                : implode("\r\n", $filas) . "\r\n";

            if (file_put_contents(
                $temporal,
                $contenido
            ) === false) {
                throw new RuntimeException(
                    'No se pudo escribir Stock.csv.'
                );
            }

            if (is_file($ruta) && !unlink($ruta)) {
                throw new RuntimeException(
                    'No se pudo reemplazar Stock.csv. ' .
                    'Verifica que no esté abierto.'
                );
            }

            if (!rename($temporal, $ruta)) {
                throw new RuntimeException(
                    'No se pudo guardar Stock.csv.'
                );
            }
        } finally {
            if (is_file($temporal)) {
                unlink($temporal);
            }
        }

        /*
         * 7. RESPUESTA DEL SERVICIO
         */
        return [
            'archivo' => $ruta,
            'total_registros' => count($filas),
            'total_columnas' => 35,
            'total_cortes' => 1,
            'productos_por_corte' => count($stockActual),
            'fecha_inicio' => $periodo['fecha_inicio'],
            'fecha_fin_solicitada' => $periodo['fecha_fin'],
            'fecha_ultimo_corte' => $hoy->format('Y-m-d'),
            'fecha_proceso' => $fechaProceso,
            'total_stock' => $totalStock,
            'total_ingresos' => $totalIngresos,
            'total_salidas' => $totalSalidas,
            'modo' => 'prueba_stock_actual',
        ];
    }
}
