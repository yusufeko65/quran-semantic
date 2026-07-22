<?php

// Konfigurasi QSE — nilai teknis; aturan epistemik ada di Manifest, bukan di sini.

return [

    // Deklarasi qira'at (Manifest §13) — ditampilkan di UI dan disertakan di export
    'qiraat' => 'Hafs \'an \'Ashim',

    // Bahasa default terjemahan & gloss (handoff #2-#3)
    'translation_lang' => 'id',
    'gloss_lang' => 'en',

    // Batas preview per lapis retrieval pada halaman kata
    'preview_limit' => 20,

    // Tier 2 — penyedia AI (titik integrasi AnalysisGenerationService::callAiApi)
    'ai' => [
        // Model TIDAK di-hardcode sbg fakta mutlak di sini -- default ini
        // adalah TITIK AWAL yang gampang diganti via .env tanpa ubah kode,
        // sesuai instruksi PM "cek dokumentasi Anthropic terkini, jangan
        // hardcode dari ingatan". Kalau ragu nama model yg benar SEKARANG,
        // cek https://docs.claude.com sebelum deploy production.
        'model'              => env('QSE_AI_MODEL', 'claude-sonnet-5'),
        'anthropic_api_key'  => env('ANTHROPIC_API_KEY'),
        'api_key'            => env('QSE_AI_API_KEY', ''),
    ],

    // Label wajib yang melekat pada setiap output AI (Bagian V butir 8, §18)
    'analysis_label' => 'HASIL ANALISA SEMENTARA',
];
