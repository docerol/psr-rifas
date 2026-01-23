<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VerifyMercadoPagoWebhook
{
    public function handle(Request $request, Closure $next)
    {
        // Obter a assinatura do cabeçalho
        $signature = $request->header('X-Signature');
        
        if (empty($signature)) {
            Log::warning('Tentativa de acesso ao webhook sem assinatura', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            abort(401, 'Assinatura não fornecida');
        }

        // Obter o ID do webhook do cabeçalho
        $webhookId = $request->header('X-Webhook-Id');
        $timestamp = $request->header('X-Webhook-Timestamp');
        $webhookSecret = config('services.mercadopago.webhook_secret');

        // Validar o timestamp (opcional, mas recomendado)
        if ($timestamp && (time() - (int)$timestamp > 300)) { // 5 minutos de tolerância
            Log::warning('Webhook com timestamp inválido', [
                'timestamp' => $timestamp,
                'webhook_id' => $webhookId
            ]);
            abort(400, 'Timestamp inválido');
        }

        // Preparar os dados para verificação
        $payload = $request->getContent();
        $signedPayload = "{$webhookId}{$timestamp}{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

        // Verificar a assinatura
        if (!hash_equals($signature, $expectedSignature)) {
            Log::warning('Assinatura do webhook inválida', [
                'webhook_id' => $webhookId,
                'expected_signature' => $expectedSignature,
                'received_signature' => $signature
            ]);
            abort(401, 'Assinatura inválida');
        }

        return $next($request);
    }
}
