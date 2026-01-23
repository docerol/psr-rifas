<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaymentWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Log do payload recebido para depuração
        Log::info('Webhook recebido do MercadoPago', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        // Processar de forma assíncrona
        ProcessPaymentWebhook::dispatch($request->all());
        
        return response()->json(['status' => 'ok']);
    }
}
