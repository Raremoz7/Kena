<?php

return [
    /** Minutos que uma reserva (hold) segura os assentos. */
    'hold_minutes' => (int) env('KENA_HOLD_MINUTES', 10),

    /** Taxa de serviço (% sobre subtotal já com desconto). */
    'service_fee_percent' => (float) env('KENA_SERVICE_FEE_PERCENT', 10),

    /** Prazo (horas antes da sessão) até quando o comprador pode pedir reembolso sozinho. */
    'refund_deadline_hours' => (int) env('KENA_REFUND_DEADLINE_HOURS', 48),

    /** Segredo HMAC dos tokens de QR dos ingressos (fallback: APP_KEY). */
    'qr_secret' => env('KENA_QR_SECRET'),

    /** Admin inicial criado pelo seeder (defina no .env, rode o seed, remova a senha). */
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrador'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'mercadopago' => [
        'access_token' => env('MP_ACCESS_TOKEN'),
        'public_key' => env('MP_PUBLIC_KEY'),
        'base_url' => env('MP_BASE_URL', 'https://api.mercadopago.com'),
        'webhook_secret' => env('MP_WEBHOOK_SECRET'),
        'pix_expiration_minutes' => (int) env('MP_PIX_EXPIRATION_MINUTES', 30),
        'statement_descriptor' => env('MP_STATEMENT_DESCRIPTOR', 'KENA INGRESSOS'),
    ],
];
