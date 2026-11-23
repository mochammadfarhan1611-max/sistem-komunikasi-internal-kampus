<?php

namespace App\Services\Unuja;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnujaApiClient
{
    public const TI_ID_SMS = '1319bfef-2188-4240-b7a3-7a507bec2e64';

    private const DATA_URL_CACHE_KEY = 'unuja_data_url';

    public function getBaseUrl(): string
    {
        return rtrim((string) config('services.unuja.base_url', ''), '/');
    }

    public function getDevId(): string
    {
        return (string) config('services.unuja.dev_id', '');
    }

    public function getToken(): string
    {
        return (string) config('services.unuja.x_token', '');
    }

    /**
     * Step 1 SSO UNUJA: Otorisasi aplikasi untuk mendapatkan URL unik akses data referensi (`data.info.urls.data`).
     */
    public function getDataUrl(bool $forceRefresh = false): ?string
    {
        if (! $forceRefresh) {
            try {
                $cached = Cache::get(self::DATA_URL_CACHE_KEY);
                if (! empty($cached) && is_string($cached)) {
                    return $cached;
                }
            } catch (\Throwable $e) {
                // Lanjutkan jika cache belum siap
            }
        }

        $baseUrl = $this->getBaseUrl();
        $devId = $this->getDevId();
        $xToken = $this->getToken();

        if ($baseUrl === '' || $devId === '' || $xToken === '') {
            Log::warning('Konfigurasi kredensial SSO UNUJA (services.unuja) belum lengkap.');

            return null;
        }

        try {
            $authResponse = Http::withoutVerifying()
                ->timeout(8)
                ->withHeaders([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($baseUrl.'/portal/data/authorize', [
                    'X-Token' => $xToken,
                    'dev_id'  => $devId,
                ]);

            $authData = $authResponse->json();

            if ($authResponse->successful() && is_array($authData)) {
                $resolvedUrl = $authData['data']['info']['urls']['data']
                    ?? $authData['data']['urls']['data']
                    ?? null;

                if (! empty($resolvedUrl) && is_string($resolvedUrl)) {
                    try {
                        Cache::put(self::DATA_URL_CACHE_KEY, $resolvedUrl, 1800);
                    } catch (\Throwable $e) {
                        // Abaikan jika cache gagal ditulis
                    }

                    return $resolvedUrl;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SSO Authorize gagal dihubungi: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Kirim satu request POST mentah ke URL data referensi SSO UNUJA.
     *
     * @param  array<string, mixed>  $payload
     */
    public function postDataRequest(array $payload, int $timeout = 15, ?string $targetUrl = null): ?Response
    {
        $url = $targetUrl ?: $this->getDataUrl();
        if (! $url) {
            return null;
        }

        return Http::withoutVerifying()
            ->timeout($timeout)
            ->withHeaders([
                'X-Token'      => $this->getToken(),
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
            ->post($url, $payload);
    }

    /**
     * Panggil Endpoint Get Data SSO UNUJA menggunakan URL unik (`data.info.urls.data`) hasil Authorize,
     * lengkap dengan dukungan pagination otomatis (`page = 1..last_page`) jika response ter-paginasi.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function fetchPortalReferenceData(array $payload): array
    {
        $targetUrl = $this->getDataUrl();
        if (! $targetUrl) {
            return [];
        }

        $payload['pagination'] = $payload['pagination'] ?? 'off';
        $xToken = $this->getToken();

        try {
            $response = Http::withoutVerifying()
                ->timeout(15)
                ->withHeaders([
                    'X-Token'      => $xToken,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post($targetUrl, $payload);

            // Jika token URL sudah kedaluwarsa (401/403/404), minta URL baru lewat authorize sekali lagi
            if (in_array($response->status(), [401, 403, 404], true)) {
                $targetUrl = $this->getDataUrl(true);
                if ($targetUrl) {
                    $response = Http::withoutVerifying()
                        ->timeout(15)
                        ->withHeaders([
                            'X-Token'      => $xToken,
                            'Content-Type' => 'application/json',
                            'Accept'       => 'application/json',
                        ])
                        ->post($targetUrl, $payload);
                }
            }

            $responseData = $response->json();

            if ($response->successful() && is_array($responseData)) {
                $rawData = $responseData['data'] ?? [];
                $allItems = [];
                $lastPage = 1;

                if (is_array($rawData)) {
                    if (array_is_list($rawData)) {
                        $allItems = array_values(array_filter($rawData, 'is_array'));
                        $lastPage = (int) ($responseData['last_page'] ?? $responseData['meta']['last_page'] ?? 1);
                    } else {
                        $pageItems = $rawData['data'] ?? $rawData['items'] ?? $rawData['list'] ?? [];
                        if (is_array($pageItems)) {
                            $allItems = array_values(array_filter($pageItems, 'is_array'));
                        }
                        $lastPage = (int) ($rawData['last_page'] ?? $responseData['last_page'] ?? 1);
                    }
                }

                // Jika API tetap mengembalikan struktur ter-paginasi (last_page > 1), tarik seluruh halaman
                if ($lastPage > 1 && empty($payload['page'])) {
                    $maxPages = min($lastPage, 50);
                    for ($page = 2; $page <= $maxPages; $page++) {
                        $pagePayload = array_merge($payload, ['page' => $page]);
                        $pageRes = Http::withoutVerifying()
                            ->timeout(15)
                            ->withHeaders([
                                'X-Token'      => $xToken,
                                'Content-Type' => 'application/json',
                                'Accept'       => 'application/json',
                            ])
                            ->post($targetUrl, $pagePayload);

                        if (! $pageRes->successful()) {
                            break;
                        }

                        $pageJson = $pageRes->json();
                        $nextData = $pageJson['data'] ?? [];
                        if (is_array($nextData) && ! array_is_list($nextData)) {
                            $nextData = $nextData['data'] ?? $nextData['items'] ?? $nextData['list'] ?? [];
                        }

                        if (! is_array($nextData) || empty($nextData)) {
                            break;
                        }

                        foreach ($nextData as $row) {
                            if (is_array($row)) {
                                $allItems[] = $row;
                            }
                        }
                    }
                }

                return $allItems;
            }
        } catch (\Throwable $e) {
            Log::warning('SSO Data Fetch gagal: '.$e->getMessage());
        }

        return [];
    }

    /**
     * Tarik `filter: mahasiswa_matakuliah` untuk sekumpulan NIM secara paralel menggunakan `Http::pool()`.
     *
     * @param  array<int, string|int>  $nimBatch
     * @return array<string, mixed>
     */
    public function fetchMahasiswaMatakuliahPool(array $nimBatch, ?string $dataUrl = null, int $timeout = 15): array
    {
        $url = $dataUrl ?: $this->getDataUrl();
        if (! $url || empty($nimBatch)) {
            return [];
        }

        $xToken = $this->getToken();

        return Http::pool(function (Pool $pool) use ($nimBatch, $url, $xToken, $timeout): void {
            foreach ($nimBatch as $nim) {
                $pool->as((string) $nim)
                    ->withoutVerifying()
                    ->timeout($timeout)
                    ->withHeaders([
                        'X-Token'      => $xToken,
                        'Accept'       => 'application/json',
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url, [
                        'filter'     => 'mahasiswa_matakuliah',
                        'nim'        => (string) $nim,
                        'pagination' => 'off',
                    ]);
            }
        });
    }

    /**
     * Helper endpoint `filter: mahasiswa`.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchMahasiswa(array $params = []): array
    {
        return $this->fetchPortalReferenceData(array_merge([
            'filter'     => 'mahasiswa',
            'pagination' => 'off',
        ], $params));
    }

    /**
     * Helper endpoint `filter: dosen`.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchDosen(array $params = []): array
    {
        return $this->fetchPortalReferenceData(array_merge([
            'filter'     => 'dosen',
            'pagination' => 'off',
        ], $params));
    }

    /**
     * Helper endpoint `filter: mahasiswa_matakuliah`.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchMahasiswaMatakuliah(array $params = []): array
    {
        return $this->fetchPortalReferenceData(array_merge([
            'filter'     => 'mahasiswa_matakuliah',
            'pagination' => 'off',
        ], $params));
    }

    /**
     * Helper endpoint `filter: dosen_matakuliah`.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchDosenMatakuliah(array $params = []): array
    {
        return $this->fetchPortalReferenceData(array_merge([
            'filter'     => 'dosen_matakuliah',
            'pagination' => 'off',
        ], $params));
    }

    /**
     * Helper endpoint `filter: jadwal_matakuliah`.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchJadwalMatakuliah(array $params = []): array
    {
        return $this->fetchPortalReferenceData(array_merge([
            'filter'     => 'jadwal_matakuliah',
            'pagination' => 'off',
        ], $params));
    }
}
