<?php

namespace App\Services\Conexus;

use ZipArchive;
use RuntimeException;

class ConexusZipService
{
    public function generar(): array
    {
        $directorio = storage_path('app/conexus/data/unificado');

        $archivos = [
            'Productos.csv',
            'Stock.csv',
            'Ventas.csv',
        ];

        $columnasEsperadas = [
            'Stock.csv' => 35,
            'Ventas.csv' => 56,
        ];

        $resumen = [];

        foreach ($archivos as $archivo) {
            $ruta = $directorio . DIRECTORY_SEPARATOR . $archivo;

            if (!is_file($ruta)) {
                throw new RuntimeException(
                    "No existe el archivo: {$archivo}"
                );
            }

            if (!is_readable($ruta)) {
                throw new RuntimeException(
                    "No se puede leer el archivo: {$archivo}"
                );
            }

            $contenido = file_get_contents($ruta);

            if ($contenido === false) {
                throw new RuntimeException(
                    "No se pudo leer el archivo: {$archivo}"
                );
            }

            if (trim($contenido) === '') {
                if ($archivo !== 'Ventas.csv') {
                    throw new RuntimeException(
                        "El archivo está vacío: {$archivo}"
                    );
                }

                $resumen[$archivo] = [
                    'registros' => 0,
                    'columnas' => 56,
                    'bytes' => filesize($ruta),
                ];

                continue;
            }

            $lineas = preg_split('/\r\n|\n|\r/', trim($contenido));
            $totalRegistros = 0;

            foreach ($lineas as $indice => $linea) {
                if ($linea === '') {
                    throw new RuntimeException(
                        "Línea vacía en {$archivo}, línea " . ($indice + 1)
                    );
                }

                if (substr($linea, -1) === '|') {
                    throw new RuntimeException(
                        "Separador adicional en {$archivo}, línea " . ($indice + 1)
                    );
                }

                $columnas = count(explode('|', $linea));

                if (
                    isset($columnasEsperadas[$archivo]) &&
                    $columnas !== $columnasEsperadas[$archivo]
                ) {
                    throw new RuntimeException(
                        "{$archivo}, línea " . ($indice + 1) .
                        ": se esperaban {$columnasEsperadas[$archivo]} columnas, " .
                        "pero se encontraron {$columnas}."
                    );
                }

                $totalRegistros++;
            }

            $resumen[$archivo] = [
                'registros' => $totalRegistros,
                'columnas' => count(explode('|', $lineas[0])),
                'bytes' => filesize($ruta),
            ];
        }

        $directorioZip = storage_path('app/conexus/zip');

        if (!is_dir($directorioZip)) {
            if (!mkdir($directorioZip, 0775, true) && !is_dir($directorioZip)) {
                throw new RuntimeException(
                    'No se pudo crear el directorio ZIP.'
                );
            }
        }

        $nombreZip = 'Output.zip';

        $rutaZip = $directorioZip . DIRECTORY_SEPARATOR . $nombreZip;

        $zip = new ZipArchive();

        if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(
                'No se pudo crear el archivo ZIP.'
            );
        }

        foreach ($archivos as $archivo) {
            $ruta = $directorio . DIRECTORY_SEPARATOR . $archivo;

            if (!$zip->addFile($ruta, $archivo)) {
                $zip->close();

                throw new RuntimeException(
                    "No se pudo agregar {$archivo} al ZIP."
                );
            }
        }

        if (!$zip->close()) {
            throw new RuntimeException(
                'No se pudo finalizar el ZIP.'
            );
        }

        // Verificar el contenido del ZIP generado.
        $verificacion = new ZipArchive();

        if ($verificacion->open($rutaZip) !== true) {
            throw new RuntimeException(
                'No se pudo verificar el ZIP generado.'
            );
        }

        foreach ($archivos as $archivo) {
            if ($verificacion->locateName($archivo) === false) {
                $verificacion->close();

                throw new RuntimeException(
                    "Falta {$archivo} dentro del ZIP."
                );
            }
        }

        $verificacion->close();

        return [
            'archivo' => $rutaZip,
            'nombre' => $nombreZip,
            'total_archivos' => count($archivos),
            'detalle' => $resumen,
            'tamano_bytes' => filesize($rutaZip),
            'fecha_generacion' => now('America/Lima')->format('Y-m-d H:i:s'),
        ];
    }
}
