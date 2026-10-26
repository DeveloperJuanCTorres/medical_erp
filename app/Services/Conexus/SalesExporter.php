<?php

namespace App\Services\Conexus;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SalesExporter
{
    private $salesService;

    public function __construct(ConexusSalesService $salesService)
    {
        $this->salesService = $salesService;
    }

    /**
     * Genera Ventas.csv.
     *
     * $resolverComercial debe devolver:
     * codigo_distribuidor, origen, codigo_cliente,
     * canal_cliente, tipo_negocio, codigo_vendedor,
     * canal_vendedor, condicion_compra y factor_conversion.
     *
     * Los códigos deben coincidir con los maestros CONEXUS.
     */
    public function exportar(
        string $fechaInicio,
        string $fechaFin,
        callable $resolverComercial
    ): array {
        if (
            !Carbon::parse($fechaInicio)->isValid() ||
            Carbon::parse($fechaInicio)->gt(Carbon::parse($fechaFin))
        ) {
            throw new RuntimeException('Periodo de ventas inválido.');
        }

        $datos = $this->salesService->obtenerMovimientos(
            $fechaInicio,
            $fechaFin
        );

        $notas = $this->salesService->obtenerNotasCredito(
            $fechaInicio,
            $fechaFin
        );

        
        $registros = collect();
        $omitidos = 0;

        foreach ($datos['ventas'] as $venta) {
            if (!in_array($venta->tipo_conexus, ['FA', 'BO'], true)) {
                $omitidos++;
                continue;
            }

            $registros->push(
                $this->normalizar($venta, false)
            );
        }

        foreach ($notas as $nota) {
            $registro = $this->normalizar($nota, true);

            $tipoOriginal = $this->clasificarDocumento(
                $registro['documento_original']
            );

            if ($tipoOriginal === null) {
                $omitidos++;
                continue;
            }

            $registro['tipo_documento_referencia'] = $tipoOriginal;

            $registros->push($registro);
        }

        $registros = $registros
            ->sortBy(function ($r) {
                return $r['fecha'] . '|' .
                    $r['documento'] . '|' .
                    $r['linea_id'];
            })
            ->values();

        $correlativos = [];
        $filas = [];
        $fechaProceso = Carbon::now('America/Lima')
            ->format('Y-m-d H:i:s');

        foreach ($registros as $registro) {
            $comercial = $resolverComercial($registro);

            $this->validarComercial($comercial, $registro);

            $claveDocumento =
                $registro['tipo_documento'] . '|' .
                $registro['documento'];

            $correlativos[$claveDocumento] =
                ($correlativos[$claveDocumento] ?? 0) + 1;

            $numeroItem = $correlativos[$claveDocumento];

            if ($numeroItem > 999) {
                throw new RuntimeException(
                    'Documento con más de 999 líneas: ' .
                    $registro['documento']
                );
            }

            $factor = (float) $comercial['factor_conversion'];

            if ($factor <= 0) {
                throw new RuntimeException(
                    'Factor de conversión inválido: ' .
                    $registro['documento']
                );
            }

            $fila = [
                // 1 - 10
                $comercial['codigo_distribuidor'],
                $registro['tipo_documento'],
                $registro['documento'],
                $registro['fecha'],
                $comercial['origen'],
                $comercial['codigo_cliente'],
                $comercial['canal_cliente'],
                $comercial['tipo_negocio'],
                $comercial['codigo_vendedor'],
                $comercial['canal_vendedor'],

                // 11 - 20
                $comercial['ruta'] ?? 'N/D',
                $numeroItem,
                $registro['codigo_producto'],
                'P',
                'N/D',
                'N/D',
                'N/D',
                'N/D',
                'N/D',
                $this->decimal($registro['cantidad']),

                // 21 - 30
                $registro['unidad_venta'],
                $this->decimal($factor),
                $this->decimal($registro['precio_unitario']),
                $this->decimal($registro['importe_sin_impuesto']),
                $this->decimal($registro['importe_con_impuesto']),
                $this->decimal($registro['descuento']),
                $comercial['condicion_compra'],
                $fechaProceso,
                $registro['tipo_documento_referencia'],
                $registro['documento_original'],

                // 31 - 41
                $registro['fecha_original'],
                'N/D',
                'N/D',
                'N/D',
                'N/D',
                'N/D',
                'N/D',
                'N/D',
                '',
                'N/D',
                $this->decimal(0),
            ];

            // 42 - 56: REF1 a REF15
            $fila = array_merge(
                $fila,
                array_fill(0, 15, 'N/D')
            );

            if (count($fila) !== 56) {
                throw new RuntimeException(
                    'Estructura incorrecta de Ventas.csv.'
                );
            }

            $fila = array_map(function ($valor) {
                $valor = (string) $valor;

                if (preg_match('/[\|\r\n]/', $valor)) {
                    throw new RuntimeException(
                        'Valor incompatible con CSV CONEXUS.'
                    );
                }

                return $valor;
            }, $fila);

            $filas[] = implode('|', $fila);
        }

        // Un período sin ventas puede ser válido.
        // Se genera Ventas.csv vacío, sin cabecera.
        if (empty($filas)) {
            \Illuminate\Support\Facades\Log::info(
                'CONEXUS - Período sin ventas elegibles',
                [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                ]
            );
        }

        
        
        $rutaDirectorio = storage_path('app/conexus/data/unificado');

        if (!is_dir($rutaDirectorio)) {
            if (!mkdir($rutaDirectorio, 0775, true) && !is_dir($rutaDirectorio)) {
                throw new RuntimeException(
                    'No se pudo crear el directorio CONEXUS.'
                );
            }
        }

        $rutaAbsoluta = $rutaDirectorio . DIRECTORY_SEPARATOR . 'Ventas.csv';

        $contenido = implode("\r\n", $filas) . "\r\n";

        $guardado = file_put_contents(
            $rutaAbsoluta,
            $contenido,
            LOCK_EX
        );

        if ($guardado === false) {
            throw new RuntimeException(
                'No se pudo guardar Ventas.csv en: ' . $rutaAbsoluta
            );
        }

        if (!is_file($rutaAbsoluta)) {
            throw new RuntimeException(
                'No se encontró Ventas.csv después de guardarlo.'
            );
        }



        return [
            'archivo' => $rutaAbsoluta,
            'total_registros' => count($filas),
            'total_columnas' => 56,
            'documentos' => count($correlativos),
            'registros_omitidos' => $omitidos,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'fecha_proceso' => $fechaProceso,
        ];
    }

    private function normalizar($linea, bool $esNota): array
    {
        $documento = trim((string) $linea->documento);

        $fecha = Carbon::parse($linea->fecha)
            ->format('Y-m-d');

        return [
            'tipo_documento' => $esNota
                ? 'NC'
                : $linea->tipo_conexus,

            'documento' => $documento,
            'fecha' => $fecha,

            'linea_id' => $esNota
                ? $linea->linea_original_id
                : $linea->linea_id,

            'cliente_id' => $linea->cliente_id,
            'codigo_cliente' => $linea->codigo_cliente,
            'codigo_producto' => $linea->codigo_producto,

            'cantidad' => $linea->cantidad_venta,
            'unidad_venta' => $linea->unidad_venta,
            'precio_unitario' => $linea->precio_unitario,

            'importe_sin_impuesto' =>
                $linea->importe_sin_impuesto,

            'importe_con_impuesto' =>
                $linea->importe_con_impuesto,

            'descuento' => 0,

            'tipo_documento_referencia' => 'N/D',

            'documento_original' => $esNota
                ? $linea->documento_original
                : 'N/D',

            'fecha_original' => $esNota
                ? Carbon::parse($linea->fecha_original)
                    ->format('Y-m-d')
                : '1900-01-01',
        ];
    }

    private function validarComercial(
        array $datos,
        array $registro
    ): void {
        $obligatorios = [
            'codigo_distribuidor',
            'origen',
            'codigo_cliente',
            'canal_cliente',
            'tipo_negocio',
            'codigo_vendedor',
            'canal_vendedor',
            'condicion_compra',
            'factor_conversion',
        ];

        foreach ($obligatorios as $campo) {
            if (
                !array_key_exists($campo, $datos) ||
                $datos[$campo] === null ||
                trim((string) $datos[$campo]) === ''
            ) {
                throw new RuntimeException(
                    "Falta {$campo} en documento " .
                    $registro['documento']
                );
            }
        }

        if (
            empty($registro['codigo_producto']) ||
            empty($registro['unidad_venta'])
        ) {
            throw new RuntimeException(
                'Producto o unidad sin identificar: ' .
                $registro['documento']
            );
        }
    }

    private function clasificarDocumento(
        string $numero
    ): ?string {
        $numero = strtoupper(trim($numero));

        if (preg_match('/^F[A-Z0-9]*\d-/', $numero)) {
            return 'FA';
        }

        if (preg_match('/^B[A-Z0-9]*\d-/', $numero)) {
            return 'BO';
        }

        return null;
    }

    private function decimal($valor): string
    {
        return number_format(
            (float) $valor,
            4,
            '.',
            ''
        );
    }
}
