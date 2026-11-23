<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestSsoApi extends Command
{
    protected $signature = 'test:api';

    protected $description = 'Test koneksi ke API SSO UNUJA berdasarkan OpenAPI docs';

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.unuja.base_url', ''), '/');
        $xToken = (string) config('services.unuja.x_token', '');
        $devId = (string) config('services.unuja.dev_id', '');

        if ($baseUrl === '' || $xToken === '' || $devId === '') {
            $this->error('Konfigurasi services.unuja (UNUJA_SSO_BASE_URL, UNUJA_X_TOKEN, UNUJA_DEV_ID) belum diisi di .env.');

            return;
        }

        $this->info('=== TEST 1: AUTHORIZE DATA ACCESS ===');
        $urlAuth = $baseUrl.'/portal/data/authorize';

        // Sesuai dokumentasi, X-Token dan dev_id dikirim di BODY
        $resAuth = Http::withoutVerifying()->withHeaders([
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($urlAuth, [
            'X-Token' => $xToken,
            'dev_id'  => $devId,
        ]);

        $this->line('URL: ' . $urlAuth);
        $this->line('Status: ' . $resAuth->status());
        $this->line('Body: ' . $resAuth->body());

        if ($resAuth->successful()) {
            $this->info("\n=== TEST 2: GET API PANDUAN ===");
            $urlPanduan = $baseUrl.'/portal/data/panduan';
            $resPanduan = Http::withoutVerifying()->post($urlPanduan);
            $this->line('URL: ' . $urlPanduan);
            $this->line('Status: ' . $resPanduan->status());

            // Tampilkan sedikit awal dari panduan untuk memastikan jalan
            $panduanBody = substr($resPanduan->body(), 0, 500) . '...';
            $this->line('Body Snippet: ' . $panduanBody);
        }
    }
}
