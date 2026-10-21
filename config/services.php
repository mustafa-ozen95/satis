<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),

        /*
         * YEDEK MODEL: birincisi "yoğunluk" dediğinde aynı ailede kalarak
         * ikinciye geçilir. Sağlayıcı değiştirmeden önce burası denenir;
         * biçim ve dil davranışı korunmuş olur.
         *
         * Aynı anahtarla bir model "UNAVAILABLE - high demand" dönerken
         * başka bir model aynı saniyede yanıtlayabiliyor.
         */
        'yedek_model' => env('GEMINI_YEDEK_MODEL', 'gemini-3.7-flash'),

        /*
         * GÖMME MODELİ. `gemini-embedding-2` daha uzun metni tek parçada
         * alıyor (8192 jeton; 001'de 2048) ve bilgi bankası kayıtlarının
         * çoğu tek parçaya sığdığı için arama isabeti artıyor.
         *
         * DEĞİŞTİRİRKEN İNDEKS SIFIRLANMALI: vektör uzayı modele özeldir,
         * eski vektörlerle yeni sorgu karşılaştırılamaz.
         *
         *     php artisan bilgi:indeksle --sifirla
         */
        'embed_model' => env('GEMINI_EMBED_MODEL', 'gemini-embedding-2'),
    ],

    /*
     * Sağlayıcıdan bağımsız çağrı kuralları (bkz. LlmService).
     *
     * ZAMAN AŞIMI 120 sn: Laravel'in varsayılanı 30 ve uzun bir teklif metni
     * bunu aşabilir; aşan istek "başarısız" sayılıp yedek modele düşer ve
     * iş iki katına çıkar.
     *
     * SICAKLIK DÜŞÜK: teklif metni yaratıcılık değil, fiyat listesine
     * sadakat istiyor.
     */
    'llm' => [
        'timeout' => (int) env('LLM_TIMEOUT', 120),
        'connect_timeout' => (int) env('LLM_CONNECT_TIMEOUT', 10),
        'embed_timeout' => (int) env('LLM_EMBED_TIMEOUT', 30),
        'retry' => (int) env('LLM_RETRY', 3),
        'retry_delay' => (int) env('LLM_RETRY_DELAY', 1500),
        'temperature' => (float) env('LLM_TEMPERATURE', 0.2),
        'max_output_tokens' => (int) env('LLM_MAX_OUTPUT_TOKENS', 8192),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    ],

    'chroma' => [
        'url' => env('CHROMA_URL', 'http://localhost:8001'),
        // Chroma 1.x sunucusu sadece /api/v2 (tenant/database) API'sini destekliyor - /api/v1 410 donuyor.
        'tenant' => env('CHROMA_TENANT', 'default_tenant'),
        'database' => env('CHROMA_DATABASE', 'default_database'),
    ],

];
