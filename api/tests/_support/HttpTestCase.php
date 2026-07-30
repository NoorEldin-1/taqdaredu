<?php

use PHPUnit\Framework\TestCase;

/**
 * Base class for HTTP-level integration/security tests that drive the live
 * backend over cURL at TEST_BASE_URL.
 *
 * The whole case skips (never fails) when the backend is unreachable, so the
 * suite stays green on a machine where only the code — not a running server +
 * DB — is available. On a live/staging environment (set TEST_BASE_URL) these
 * exercise the real request → JWT → response contract end to end.
 */
abstract class HttpTestCase extends TestCase
{
    /** @var bool|null Cached reachability result for the process. */
    private static $serverUp;

    protected function setUp(): void
    {
        if (!$this->serverReachable()) {
            $this->markTestSkipped(
                'Backend at ' . TEST_BASE_URL . ' unreachable — skipping HTTP test. '
                . 'Start XAMPP (or set TEST_BASE_URL) to enable live integration tests.'
            );
        }
    }

    protected function serverReachable(): bool
    {
        if (self::$serverUp !== null) {
            return self::$serverUp;
        }
        $res = $this->request('GET', '/api/api_frontend/settings');
        // "Usable" means the API app actually answers — the public settings
        // endpoint returns 200 with a JSON envelope. A generic 404 (e.g. Apache
        // up but the app docroot/DB not wired) means we must skip, not fail.
        self::$serverUp = $res['code'] === 200
            && is_array($res['json'])
            && array_key_exists('status', $res['json']);
        return self::$serverUp;
    }

    /**
     * Perform an HTTP request against the backend.
     *
     * @param string $method  GET|POST|PUT|DELETE
     * @param string $path    Path beginning with '/'
     * @param array  $fields  Form fields (url-encoded) for write methods
     * @param array  $headers Extra request headers ("Name: value")
     * @return array{code:int, body:string, json:mixed, headers:string}
     */
    protected function request(string $method, string $path, array $fields = [], array $headers = []): array
    {
        $ch = curl_init(TEST_BASE_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if (!empty($fields)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $raw        = curl_exec($ch);
        $code       = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = $raw === false ? '' : substr($raw, 0, $headerSize);
        $body       = $raw === false ? '' : substr($raw, $headerSize);

        return [
            'code'    => $code,
            'body'    => $body,
            'json'    => json_decode($body, true),
            'headers' => $rawHeaders,
        ];
    }
}
