<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configurações de Pedidos
    |--------------------------------------------------------------------------
    |
    | Aqui você pode configurar as opções relacionadas a pedidos, como tempo
    | de expiração e limites de tentativas.
    |
    */
    'order' => [
        // Tempo em minutos até um pedido reservado expirar
        'expiration' => (int) env('ORDER_EXPIRATION_MINUTES', 60),
        
        // Número máximo de tentativas de processamento de pagamento
        'max_attempts' => (int) env('ORDER_MAX_ATTEMPTS', 3),
        
        // Tempo de espera entre tentativas (em minutos)
        'retry_after' => (int) env('ORDER_RETRY_AFTER_MINUTES', 10),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Configurações de Pagamento
    |--------------------------------------------------------------------------
    |
    | Configurações relacionadas ao processamento de pagamentos.
    |
    */
    'gateway' => [
        // Gateway de pagamento padrão (ex: 'mercadopago', 'pagseguro', 'stripe')
        'default' => env('PAYMENT_GATEWAY', 'mercadopago'),
        
        // Configurações específicas do Mercado Pago
        'mercadopago' => [
            'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
            'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
            'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
            'sandbox' => (bool) env('MERCADOPAGO_SANDBOX', true),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Configurações de Notificação
    |--------------------------------------------------------------------------
    |
    | Configurações relacionadas a notificações de pedidos e pagamentos.
    |
    */
    'notifications' => [
        // Habilitar notificações por e-mail
        'email' => (bool) env('NOTIFY_EMAIL', true),
        
        // Habilitar notificações por SMS
        'sms' => (bool) env('NOTIFY_SMS', false),
        
        // E-mail do administrador para notificações importantes
        'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),
    ],
];
