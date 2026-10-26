<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Equivalencias comerciales CONEXUS 3.2
    |--------------------------------------------------------------------------
    | Estos valores deben ser consistentes entre
    | Clientes.csv, Vendedores.csv y Ventas.csv.
    */

    
    'clientes' => [
        'equivalencias' => [
            21 => [
                'canal' => 'CAN001',
                'tipo_negocio' => 'NEG001',
            ],
        ],

        'predeterminado' => [
            'canal' => null,
            'tipo_negocio' => null,
        ],
    ],


    
    'vendedores' => [
        'equivalencias' => [],

        'predeterminado' => [
            'codigo' => 'VEN001',
            'canal' => 'CAN001',
        ],
    ],


        
    'condiciones_compra' => [
        'por_estado_pago' => [
            'paid'    => 'CONTADO',
            'due'     => 'CREDITO',
            'partial' => 'CREDITO',
        ],
    ],


];
