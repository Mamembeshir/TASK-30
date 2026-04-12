<?php

namespace App\Services;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client used by Livewire components to consume the REST /api/* endpoints.
 *
 * The API routes use the `auth:web` (session) guard. We forward the current
 * request's Cookie header so the loopback call authenticates as the same user
 * without a separate token exchange.
 *
 * In the `testing` environment there is no running HTTP server, so requests are
 * routed through the application's HTTP kernel in-process. The auth guard is
 * already populated by `actingAs()` / `Livewire::actingAs()` and persists as a
 * singleton, so the middleware stack authenticates correctly without a session
 * cookie on the synthetic request.
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

    /**
     * Route a request through the HTTP kernel in-process (test environment only).
     *
     * Data is passed as standard form parameters (populating $request->request /
     * $request->query directly) rather than a raw JSON body — this avoids
     * any Content-Type / body-parsing subtleties while keeping Laravel's
     * $request->validate() / $request->input() fully functional.
     *
     * Saves and restores the global request binding so nested calls do not
     * pollute outer Livewire component state.
     */
    private function kernelRequest(string $method, string $path, array $data): Response
    {
        $originalRequest = app('request');

        // Livewire's test RequestBroker calls withoutMiddleware() before dispatching
        // component actions, setting middleware.disable=true in the container. If we
        // leave that flag set when running the kernel in-process, SubstituteBindings
        // (and auth middleware) are skipped and route model binding breaks. We remove
        // the flag for the duration of our kernel call and restore it afterwards.
        $middlewareWasDisabled = app()->bound('middleware.disable') && app()->make('middleware.disable') === true;
        if ($middlewareWasDisabled) {
            unset(app()['middleware.disable']);
        }

        $upper  = strtoupper($method);
        $server = [
            'HTTP_ACCEPT'           => 'application/json',
            'HTTP_ORIGIN'           => config('app.url'),
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            // Content-Type: application/json lets VerifyApiCsrfToken grant the
            // same-origin JSON exemption (isJsonRequest checks Content-Type +
            // matching Origin header).
            'CONTENT_TYPE'          => 'application/json',
        ];

        // Pass data as JSON body — Laravel reads it via $request->json() when
        // Content-Type is application/json, which is what $request->input() /
        // $request->validate() uses for JSON requests.
        $internalRequest = \Illuminate\Http\Request::create(
            '/api' . $path,
            $upper,
            $upper === 'GET' ? $data : [],  // query for GET; body carries POST/PUT
            [],                             // cookies
            [],                             // files
            $server,
            $upper !== 'GET' ? json_encode($data) : null
        );

        /** @var \Illuminate\Foundation\Http\Kernel $kernel */
        $kernel   = app(\Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($internalRequest);

        // Restore middleware.disable so Livewire's subsequent test assertions work.
        if ($middlewareWasDisabled) {
            app()->instance('middleware.disable', true);
        }

        // Restore the outer request so Livewire's component state is unaffected.
        app()->instance('request', $originalRequest);

        return new Response(new PsrResponse(
            $response->getStatusCode(),
            ['Content-Type' => 'application/json'],
            $response->getContent()
        ));
    }

    public function get(string $path, array $query = []): Response
    {
        if (getenv('APP_ENV') === 'testing') {
            return $this->kernelRequest('GET', $path, $query);
        }

        return $this->http()->get('/api' . $path, $query);
    }

    public function post(string $path, array $data = []): Response
    {
        if (getenv('APP_ENV') === 'testing') {
            return $this->kernelRequest('POST', $path, $data);
        }

        return $this->http()->post('/api' . $path, $data);
    }

    public function put(string $path, array $data = []): Response
    {
        if (getenv('APP_ENV') === 'testing') {
            return $this->kernelRequest('PUT', $path, $data);
        }

        return $this->http()->put('/api' . $path, $data);
    }

    /**
     * POST with a file attachment (multipart/form-data).
     *
     * In the `testing` environment the request is routed through the HTTP kernel
     * in-process (no running server needed). The file is passed directly as an
     * UploadedFile so the controller receives it via $request->file().
     *
     * @param  string  $path
     * @param  array   $data   Other form fields
     * @param  string  $field  Form field name for the file
     * @param  \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|\Symfony\Component\HttpFoundation\File\UploadedFile  $file
     */
    public function postWithFile(string $path, array $data, string $field, $file): Response
    {
        if (getenv('APP_ENV') === 'testing') {
            return $this->kernelRequestWithFile($path, $data, $field, $file);
        }

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

    /**
     * Route a multipart file-upload request through the HTTP kernel in-process.
     *
     * The synthetic Request is built with the UploadedFile object injected
     * directly into the files bag, bypassing the real HTTP multipart parser.
     * VerifyApiCsrfToken grants an exemption for in-process kernel requests in
     * the test environment via the X-Internal-Kernel-Request header.
     */
    private function kernelRequestWithFile(string $path, array $data, string $field, $file): Response
    {
        $originalRequest = app('request');

        $middlewareWasDisabled = app()->bound('middleware.disable') && app()->make('middleware.disable') === true;
        if ($middlewareWasDisabled) {
            unset(app()['middleware.disable']);
        }

        $server = [
            'HTTP_ACCEPT'                    => 'application/json',
            'HTTP_ORIGIN'                    => config('app.url'),
            'HTTP_X_REQUESTED_WITH'          => 'XMLHttpRequest',
            // Signals VerifyApiCsrfToken to bypass the CSRF check for this
            // trusted in-process request (gated on runningUnitTests()).
            'HTTP_X_INTERNAL_KERNEL_REQUEST' => '1',
        ];

        $internalRequest = \Illuminate\Http\Request::create(
            '/api' . $path,
            'POST',
            $data,       // form fields
            [],          // cookies
            [$field => $file],  // files
            $server
        );

        /** @var \Illuminate\Foundation\Http\Kernel $kernel */
        $kernel   = app(\Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($internalRequest);

        if ($middlewareWasDisabled) {
            app()->instance('middleware.disable', true);
        }

        app()->instance('request', $originalRequest);

        return new Response(new PsrResponse(
            $response->getStatusCode(),
            ['Content-Type' => 'application/json'],
            $response->getContent()
        ));
    }
}
