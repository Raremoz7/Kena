<?php

return [
    /** Minutos que uma reserva (hold) segura os assentos. */
    'hold_minutes' => (int) env('KENA_HOLD_MINUTES', 10),

    /**
     * Máximo de ingressos por CPF em uma sessão (reserva atual + já emitidos,
     * cortesias incluídas). O CPF é único por conta, então contar pela conta é
     * contar pelo CPF. Impede que uma pessoa (ou robô) segure a plateia inteira.
     */
    'max_tickets_per_buyer' => (int) env('KENA_MAX_TICKETS_PER_BUYER', 8),

    /**
     * Taxa da plataforma nos ingressos (% sobre o valor pago, já com desconto).
     * Interna: não soma no preço do comprador; sai do que o organizador recebe.
     */
    'service_fee_percent' => (float) env('KENA_SERVICE_FEE_PERCENT', 6),

    /**
     * Taxa da plataforma nas fotos (% sobre o valor das fotos), independente da
     * do ingresso e também interna. Cada organização pode ter a sua (/admin).
     */
    'photo_fee_percent' => (float) env('KENA_PHOTO_FEE_PERCENT', 6),

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

        /**
         * "Conectar com Mercado Pago" (OAuth): a venda cai na conta do
         * organizador e a taxa de serviço vai para a plataforma como
         * application_fee. DESLIGADO por padrão: ligar só com aprovação formal.
         * Ligado, o token e a Public Key digitados no painel/.env deixam de valer.
         */
        'oauth' => [
            'enabled' => (bool) env('KENA_MP_OAUTH_ENABLED', false),
            'client_id' => env('MP_OAUTH_CLIENT_ID'),
            'client_secret' => env('MP_OAUTH_CLIENT_SECRET'),
            // Vazio = rota do callback do painel (precisa estar cadastrada igual na aplicação do MP).
            'redirect_uri' => env('MP_OAUTH_REDIRECT_URI'),
            'auth_url' => env('MP_OAUTH_AUTH_URL', 'https://auth.mercadopago.com/authorization'),
            // Exige "PKCE" habilitado nos detalhes da aplicação do MP.
            'pkce' => (bool) env('MP_OAUTH_PKCE', true),
            // true = credenciais de teste (sandbox) na troca do code.
            'test_token' => (bool) env('MP_OAUTH_TEST_TOKEN', false),
            // Renova o token quando faltar menos que isso para vencer (validade: 180 dias).
            'refresh_before_days' => (int) env('MP_OAUTH_REFRESH_BEFORE_DAYS', 30),
        ],
    ],

    /**
     * Fotos do espetáculo. Prévias, miniaturas e rostos ficam em disco privado
     * (só saem para quem tem acesso à galeria). Os originais em alta ficam fora
     * da pasta pública: o download passa pelo servidor, que confere a compra.
     */
    'photos' => [
        'disk' => env('PHOTOS_DISK', 'photos'),
        // Titular do direito autoral no aviso das prévias quando a galeria não define um.
        'copyright_holder' => env('KENA_PHOTOS_COPYRIGHT_HOLDER', 'Kena'),
        // Pasta local dos originais (o `original_ref` da foto é o caminho dentro dela).
        'originals_path' => env('KENA_PHOTOS_ORIGINALS_PATH', storage_path('app/private/fotos-originais')),
        // Google Drive: JSON da conta de serviço (caminho absoluto ou relativo à raiz do projeto), fora do git.
        'drive' => [
            'credentials' => env('GOOGLE_DRIVE_CREDENTIALS'),
        ],
    ],

    /**
     * Cadastro aberto de organizadores (multi-tenant). DESLIGADO por padrão:
     * ligar só com aprovação formal. Também exige o OAuth do Mercado Pago ligado.
     */
    'organizer_signup' => [
        'enabled' => (bool) env('KENA_ORGANIZER_SIGNUP_ENABLED', false),
    ],

    /** Canal de atendimento exibido em Ajuda, Termos e Privacidade. */
    'support' => [
        'email' => env('KENA_SUPPORT_EMAIL'),
        'whatsapp' => env('KENA_SUPPORT_WHATSAPP'), // com DDI e DDD, ex.: 5561999999999
    ],

    /** Quem opera a plataforma (controlador dos dados na LGPD). */
    'legal' => [
        'name' => env('KENA_LEGAL_NAME', 'Somo'),
        'document' => env('KENA_LEGAL_DOCUMENT'), // CNPJ ou CPF
        // Endereço físico do fornecedor: o Decreto 7.962/2013 (comércio
        // eletrônico) pede nome, CNPJ/CPF e endereço em local de destaque.
        'address' => env('KENA_LEGAL_ADDRESS'),
        // Encarregado de dados (LGPD art. 41): identidade e contato públicos.
        'dpo_name' => env('KENA_DPO_NAME'),
        'dpo_email' => env('KENA_DPO_EMAIL'),
        'updated_at' => env('KENA_LEGAL_UPDATED_AT', '22/10/2026'),
    ],
];
