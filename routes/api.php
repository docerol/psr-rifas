<?php

use App\Http\Controllers\Api\RifaController;
use App\Http\Controllers\Api\TicketPurchaseController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Rotas da API para Rifas
Route::prefix('rifas')->group(function () {
    // Rotas públicas
    Route::get('/', [RifaController::class, 'index']);
    Route::get('/{rifa}', [RifaController::class, 'show']);

    // Rotas protegidas por autenticação
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/', [RifaController::class, 'store']);
        Route::put('/{rifa}', [RifaController::class, 'update']);
        Route::delete('/{rifa}', [RifaController::class, 'destroy']);
    });
});

// Rotas para compra de bilhetes
Route::prefix('tickets')->group(function () {
    // Rota para reservar bilhetes (pode ser acessada sem autenticação)
    Route::post('/reserve', [TicketPurchaseController::class, 'reserveTickets']);
    
    // Rotas protegidas por autenticação
    Route::middleware(['auth:sanctum'])->group(function () {
        // Rota para confirmar pagamento (requer autenticação)
        Route::post('/{order}/confirm-payment', [TicketPurchaseController::class, 'confirmPayment'])
            ->name('tickets.confirm-payment');
    });
});

// Rotas de webhook
Route::prefix('webhooks')->group(function () {
    Route::post('mercadopago', [WebhookController::class, 'handle'])
        ->middleware('verify.mercadopago')
        ->name('webhooks.mercadopago');
});
