<?php

use App\Http\Controllers\Api\ConexusController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\ConexusZipController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


/*
|--------------------------------------------------------------------------
| CONEXUS 3.2 - MEDICAL DRUG
|--------------------------------------------------------------------------
*/

Route::prefix('conexus')
    ->middleware('throttle:30,1')
    ->group(function () {

        Route::get(
            '/estado',
            [\App\Http\Controllers\Api\ConexusController::class, 'estado']
        );

        Route::post(
            '/resumen',
            [\App\Http\Controllers\Api\ConexusController::class, 'resumen']
        );

        Route::get(
            '/productos',
            [\App\Http\Controllers\Api\ConexusController::class, 'productos']
        );

        Route::get(
            '/presentaciones',
            [\App\Http\Controllers\Api\ConexusController::class, 'presentaciones']
        );

        Route::post(
            '/exportar-productos',
            [\App\Http\Controllers\Api\ConexusController::class, 'exportarProductos']
        );

        Route::post('/periodo', [\App\Http\Controllers\Api\ConexusController::class, 'periodo']);

        Route::get('/stock-actual', [\App\Http\Controllers\Api\ConexusController::class, 'stockActual']);

        Route::post('/movimientos-stock', [\App\Http\Controllers\Api\ConexusController::class, 'movimientosStock']);

        Route::post('/exportar-stock', [\App\Http\Controllers\Api\ConexusController::class, 'exportarStock']);

        Route::post('/exportar-ventas', [\App\Http\Controllers\Api\ConexusController::class, 'exportarVentas']);

        Route::post('/generar-zip', [ConexusZipController::class, 'generar']);
        Route::get('/descargar-zip', [ConexusZipController::class, 'descargar']);

    });