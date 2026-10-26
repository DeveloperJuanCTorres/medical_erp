<?php

namespace App\Services\Conexus;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ProductExporter
{
    private ConexusProductCatalog $catalogo;

    public function __construct(ConexusProductCatalog $catalogo)
    {
        $this->catalogo = $catalogo;
    }

    public function exportar(): array
    {
        $proveedor = trim((string) config('conexus.provider_code'));
        $distribuidor = trim((string) config('conexus.distributor_code'));
        $modoPrueba = (bool) config('conexus.test_mode', true);

        if ($proveedor === '' || $distribuidor === '') {
            throw new RuntimeException(
                'Faltan los códigos de proveedor o distribuidor CONEXUS.'
            );
        }

        if (!$modoPrueba && (
            str_starts_with($proveedor, 'TEST') ||
            str_starts_with($distribuidor, 'TEST')
        )) {
            throw new RuntimeException(
                'No se pueden utilizar códigos temporales fuera del modo de prueba.'
            );
        }

        $productos = $this->catalogo->obtenerPresentaciones();

        if (empty($productos)) {
            throw new RuntimeException(
                'No se encontraron presentaciones UNILEVER.'
            );
        }

        $directorio = storage_path('app/conexus/data/unificado');

        File::ensureDirectoryExists($directorio);

        $ruta = $directorio . DIRECTORY_SEPARATOR . 'Productos.csv';

        $temporal = tempnam($directorio, 'productos_');

        if ($temporal === false) {
            throw new RuntimeException(
                'No se pudo crear el archivo temporal.'
            );
        }

        $archivo = fopen($temporal, 'wb');

        if (!$archivo) {
            @unlink($temporal);
            throw new RuntimeException(
                'No se pudo abrir el archivo temporal.'
            );
        }

        $fechaProceso = Carbon::now(
            config('conexus.timezone', 'America/Lima')
        )->format('Y-m-d H:i:s');

        $total = 0;
        $codigos = [];

        try {
            foreach ($productos as $producto) {

                $codigo = trim((string) $producto['codigo_producto']);
                $nombre = trim((string) $producto['nombre_producto']);

                $precioCompra = $producto['precio_compra_sin_igv'];
                $precioVenta = $producto['precio_venta_sin_igv'];

                if (
                    $precioCompra === null ||
                    $precioVenta === null ||
                    !is_numeric($precioCompra) ||
                    !is_numeric($precioVenta)
                ) {
                    throw new \RuntimeException(
                        'Precios no configurados para el SKU: ' .
                        $producto['codigo_producto']
                    );
                }

                if ((float) $precioCompra < 0 || (float) $precioVenta < 0) {
                    throw new \RuntimeException(
                        'Precios negativos para el SKU: ' .
                        $producto['codigo_producto']
                    );
                }

                $precioCompra = number_format(
                    (float) $precioCompra, 4, '.', ''
                );

                $precioSugerido = number_format(
                    (float) $precioVenta, 4, '.', ''
                );

                if ($codigo === '' || $nombre === '') {
                    throw new RuntimeException(
                        'Existe una presentación sin código o nombre.'
                    );
                }

                if (isset($codigos[$codigo])) {
                    throw new RuntimeException(
                        'Código de producto duplicado: ' . $codigo
                    );
                }

                $codigos[$codigo] = true;

                if (mb_strlen($codigo) > 25) {
                    throw new RuntimeException(
                        'El código excede 25 caracteres: ' . $codigo
                    );
                }

                if (mb_strlen($nombre) > 100) {
                    throw new RuntimeException(
                        'El nombre excede 100 caracteres: ' . $codigo
                    );
                }

                /*
                 * Campos básicos de CONEXUS.
                 *
                 * Los valores aún no confirmados se mantienen
                 * pendientes y bloquean la exportación definitiva.
                 */

                $codigoProductoProveedor = $codigo;
                $nombreProductoProveedor = $nombre;

                // Valor provisional: debe confirmarse el peso real.
                $peso = '0.0000';

                // Valor provisional: validar el tratamiento tributario.
                $afecto = '1';

                $fila = [
                    $distribuidor,                 // CodigoDistribuidor
                    $proveedor,                    // CodigoProveedor
                    $codigo,                       // CodigoProducto
                    $nombre,                       // NombreProducto
                    'UNIDAD',                      // UnidadCompra
                    'UNIDAD',                      // UnidadVenta
                    '1.0000',                      // FactorConversionUnidadVenta
                    'UNIDAD',                      // UnidadInventario
                    '1.0000',                      // FactorConversionUnidadInventario
                    'UNIDAD',                      // UnidadReporte (provisional)
                    '1.0000',                      // FactorConversionUnidadReporte
                    $peso,                         // Peso (provisional)
                    'P',                           // FlagBonificado
                    $afecto,                       // Afecto (provisional)
                    $fechaProceso,                 // FechaProceso
                    $codigoProductoProveedor,      // CodigoProductoProveedor
                    $nombreProductoProveedor,      // NombreProductoProveedor

                    'N/D',                         // CodCategoria
                    'N/D',                         // Categoria
                    'N/D',                         // CodPresentacion
                    'N/D',                         // Presentacion
                    '22',                          // CodMarca
                    'UNILEVER',                    // Marca
                    $producto['estado'],           // Estado
                    'N/D',                         // EAN
                    'N/D',                         // DUN
                    $precioCompra,                 // PrecioCompra real
                    $precioSugerido,               // PrecioSugerido provisionalmente asociado al precio de venta
                    '0.0000',                      // PrecioPromedio pendiente
                    'N/D',                         // CodLinea
                    'N/D',                         // Linea
                ];

                // REF1 a REF15
                for ($i = 1; $i <= 15; $i++) {
                    $fila[] = 'N/D';
                }

                $fila = array_map(function ($valor) {
                    $valor = (string) $valor;

                    return str_replace(
                        ["|", "\r", "\n"],
                        [' ', ' ', ' '],
                        $valor
                    );
                }, $fila);

                if (count($fila) !== 46) {
                    throw new RuntimeException(
                        'Cantidad incorrecta de columnas en Productos.csv.'
                    );
                }

                fwrite($archivo, implode('|', $fila) . "\r\n");

                $total++;
            }
        } catch (\Throwable $e) {
            fclose($archivo);
            @unlink($temporal);
            throw $e;
        }

        fclose($archivo);

        if (!rename($temporal, $ruta)) {
            @unlink($temporal);
            throw new RuntimeException(
                'No se pudo publicar Productos.csv.'
            );
        }

        return [
            'archivo' => 'Productos.csv',
            'ruta' => $ruta,
            'total_registros' => $total,
            'modo_prueba' => $modoPrueba,
            'codigos_temporales' => $modoPrueba,
            'estado' => 'generado_para_validacion',
        ];
    }
}