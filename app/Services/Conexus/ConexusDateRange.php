<?php

namespace App\Services\Conexus;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ConexusDateRange
{
    public function resolver(
        ?string $fechaInicio = null,
        ?string $fechaFin = null
    ): array {
        $timezone = config('conexus.timezone', 'America/Lima');

        $hoy = CarbonImmutable::now($timezone);

        // Sin fechas: primer día del mes anterior
        // hasta el último día del mes actual.
        if (empty($fechaInicio) && empty($fechaFin)) {
            $inicio = $hoy->subMonthNoOverflow()->startOfMonth();
            $fin = $hoy->endOfMonth();
        } else {
            if (empty($fechaInicio) || empty($fechaFin)) {
                throw new InvalidArgumentException(
                    'Debes proporcionar ambas fechas o ninguna.'
                );
            }

            $inicio = $this->parsearFecha($fechaInicio, $timezone);
            $fin = $this->parsearFecha($fechaFin, $timezone);
        }

        if ($inicio->greaterThan($fin)) {
            throw new InvalidArgumentException(
                'La fecha inicial no puede ser posterior a la fecha final.'
            );
        }

        return [
            'fecha_inicio' => $inicio->format('Y-m-d'),
            'fecha_fin' => $fin->format('Y-m-d'),
            'desde' => $inicio->startOfDay()->format('Y-m-d H:i:s'),
            'hasta_exclusivo' => $fin->addDay()->startOfDay()
                ->format('Y-m-d H:i:s'),
        ];
    }

    private function parsearFecha(
        string $fecha,
        string $timezone
    ): CarbonImmutable {
        $fecha = trim($fecha);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException(
                'Las fechas deben tener formato YYYY-MM-DD.'
            );
        }

        $valor = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $fecha,
            $timezone
        );

        if (!$valor || $valor->format('Y-m-d') !== $fecha) {
            throw new InvalidArgumentException(
                'Fecha inválida: ' . $fecha
            );
        }

        return $valor;
    }
}
