<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuración de integración CONEXUS 3.2
    |--------------------------------------------------------------------------
    */

    'enabled' => env('CONEXUS_ENABLED', false),

    'business_id' => (int) env('CONEXUS_BUSINESS_ID', 1),

    'api_token' => env('CONEXUS_API_TOKEN', ''),

    'brand_name' => env('CONEXUS_BRAND_NAME', 'UNILEVER'),

    /*
    |--------------------------------------------------------------------------
    | Exportación
    |--------------------------------------------------------------------------
    */

    'output_directory' => 'conexus/data/unificado',

    'timezone' => 'America/Lima',

    /*
    |--------------------------------------------------------------------------
    | Códigos de integración
    |--------------------------------------------------------------------------
    */

    'provider_code' => env('CONEXUS_PROVIDER_CODE', ''),

    'distributor_code' => env('CONEXUS_DISTRIBUTOR_CODE', ''),

    'test_mode' => env('CONEXUS_TEST_MODE', true),

    
    'ventas' => [
        'origen' => env('CONEXUS_VENTAS_ORIGEN', 'OFICINA'),
        'canal_cliente' => env('CONEXUS_CANAL_CLIENTE'),
        'tipo_negocio' => env('CONEXUS_TIPO_NEGOCIO'),
        'codigo_vendedor' => env('CONEXUS_CODIGO_VENDEDOR'),
        'canal_vendedor' => env('CONEXUS_CANAL_VENDEDOR'),
        'condicion_compra' => env('CONEXUS_CONDICION_COMPRA'),
    ],


];