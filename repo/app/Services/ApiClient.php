<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client used by Livewire components to consume the REST /api/* endpoints.
 *
 * The API routes use the `auth:web` (session) guard. We forward the current
 * request's Cookie header so the loopback call authenticates as the same user
 * without a separate token exchange.
 */
class ApiClient
{
    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl(config('app.url'))
            ->acceptJson()
            ->withHeaders([
                'Cookie'           => request()->header('Cookie', ''),
                'X-Requested-With' => 'XMLHttpRequest',
            ]);
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->http()->get('/api' . $path, $query);
    }

    public function post(string $path, array $data = []): Response
    {
        return $this->http()->post('/api' . $path, $data);
    }

    public function put(string $path, array $data = []): Response
    {
        return $this->http()->put('/api' . $path, $data);
    }

    /**
     * POST with a file attachment (multipart/form-data).
     *
     * @param  string  $path
     * @param  array   $data   Other form fields
     * @param  string  $field  Form field name for the file
     * @param  \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|\Symfony\Component\HttpFoundation\File\UploadedFile  $file
     */
    public function postWithFile(string $path, array $data, string $field, $file): Response
    {
        $pending = $this->http()->attach(
            $field,
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName(),
            ['Content-Type' => $file->getMimeType()]
        );

        // Pass remaining data fields as form params
        foreach ($data as $key => $value) {
            $pending = $pending->attach($key, (string) $value);
        }

        return $pending->post('/api' . $path);
    }
}
